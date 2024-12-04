<?php

namespace LLPhant\Embeddings\DataReader;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class QwenOcrReader
{
    private string $file_mime_type;
    private string $file_base64;

    public function getText(string $path, string $file_mime_type): string|null
    {
        $this->file_mime_type = $file_mime_type;
        $this->file_base64 = base64_encode(file_get_contents($path));

        $psr18client = new GuzzleClient([]);

        $sdk = OpenAI::factory()
            ->withApiKey(pf_get_config_conf_or_fail('DASHSCOPE_API_KEY_4_OCR'))
            ->withBaseUri('https://dashscope.aliyuncs.com/compatible-mode/v1')
            ->withHttpClient($psr18client)
            ->withStreamHandler(fn(RequestInterface $psr7req): ResponseInterface => $psr18client->send($psr7req, [
                'stream' => true,
            ]))
            ->make();

        if (str_contains(pf_get_config_conf_or_fail('OCR_MODEL'), 'qwen-vl-ocr')) {
            // https://help.aliyun.com/zh/model-studio/user-guide/vision#da33480805fjh
            // https://help.aliyun.com/zh/model-studio/user-guide/vision#09633c7e9brr6
            // text：建议指定为 `Read all text in the image.`，设置为该值可以获得最好的识别效果。
            $postJson = $this->chat_params('Read all text in the image.');
            $postJson['max_tokens'] = 4000;
        } else {
            $postJson = $this->chat_params(<<<TXT
You are good at dealing with OCR (Optical Character Recognition) tasks.

Please extract all text from the image, including both printed and handwritten text.
Ensure all visible text, whether clear or cursive handwriting, is accurately captured.
Ignore any emojis, icons, or non-text content such as images or graphics.
Return only the extracted text, preserving its original structure, without any additional commentary or interpretation.
TXT
            );
        }

        $response = $sdk->chat()->create($postJson);

        return $response->choices[0]->message->content;
    }

    private function chat_params(string $user_text): array
    {
        $user_text = trim($user_text);

        return [
            'model' => pf_get_config_conf_or_fail('OCR_MODEL'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => "data:{$this->file_mime_type};base64,{$this->file_base64}",
                        ],
                        [
                            'type' => 'text',
                            'text' => $user_text,
                        ],
                    ],
                ],
            ],
            'top_p' => 0.01,
            'temperature' => 0.1,
            'repetition_penalty' => 1.05,
        ];
    }
}
