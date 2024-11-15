<?php

namespace LLPhant\Embeddings\DataReader;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class QwenOcrReader
{
    public static function getText(string $path, string $file_mime_type): string|null
    {
        $file_base64 = base64_encode(file_get_contents($path));

        $psr18client = new GuzzleClient([]);

        $sdk = OpenAI::factory()
            ->withApiKey(env('DASHSCOPE_API_KEY_4_OCR'))
            ->withBaseUri('https://dashscope.aliyuncs.com/compatible-mode/v1')
            ->withHttpClient($psr18client)
            ->withStreamHandler(fn(RequestInterface $psr7req): ResponseInterface => $psr18client->send($psr7req, [
                'stream' => true,
            ]))
            ->make();

        // https://help.aliyun.com/zh/model-studio/user-guide/vision#09633c7e9brr6
        $response = $sdk->chat()->create([
            'model' => env('OCR_MODEL'),
            'messages' => [
                // [
                //     'role' => 'system',
                //     'content' => 'You are a helpful assistant.',
//                     'content' => <<<TXT
// You are good at dealing with Optical Character Recognition (OCR) tasks.
// Please extract all text from the image, including both printed and handwritten text.
// Ensure all visible text, whether clear or cursive handwriting, is accurately captured.
// Ignore any emojis, icons, or non-text content such as images or graphics.
// Return only the extracted text, preserving its original structure, without any additional commentary or interpretation.
// TXT,
                // ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => "data:$file_mime_type;base64,$file_base64",
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Read all text in the image.', // text：建议指定为 `Read all text in the image.`，设置为该值可以获得最好的识别效果。
                        ],
                    ],
                ],
            ],
            'top_p' => 0.01,
            'temperature' => 0.1,
            'repetition_penalty' => 1.05,
            'max_tokens' => 4000,
        ]);

        return $response->choices[0]->message->content;
    }
}
