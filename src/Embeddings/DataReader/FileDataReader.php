<?php

namespace LLPhant\Embeddings\DataReader;

use LLPhant\Embeddings\Document;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Mime\MimeTypes;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;
use Throwable;

final class FileDataReader implements DataReader
{
    public string $sourceType = 'files';

    /**
     * @template T of Document
     *
     * @param  class-string<T>  $documentClassName
     * @param  string[]  $extensions
     */
    public function __construct(public string $filePath, public readonly string $documentClassName = Document::class, private readonly array $extensions = [])
    {
    }

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        // If it's a directory
        if (is_dir($this->filePath)) {
            return $this->getDocumentsFromDirectory($this->filePath);
        }
        // If it's a file
        $content = $this->getContentFromFile($this->filePath);
        if ($content === false) {
            return [];
        }

        return [$this->getDocument($content, $this->filePath)];
    }

    /**
     * @return Document[]
     */
    private function getDocumentsFromDirectory(string $directory): array
    {
        $documents = [];
        // Open the directory
        if ($handle = opendir($directory)) {
            // Read the directory contents
            while (($entry = readdir($handle)) !== false) {
                $fullPath = $directory.'/'.$entry;
                if ($entry != '.' && $entry != '..') {
                    if (is_dir($fullPath)) {
                        $documents = [...$documents, ...$this->getDocumentsFromDirectory($fullPath)];
                    } else {
                        $content = $this->getContentFromFile($fullPath);
                        if ($content !== false) {
                            $documents[] = $this->getDocument($content, $entry);
                        }
                    }
                }
            }

            // Close the directory
            closedir($handle);
        }

        return $documents;
    }

    private function getContentFromFile(string $path): string|false
    {
        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! $this->validExtension($fileExtension)) {
            return false;
        }

        $file_mime_type = (new MimeTypes())->guessMimeType($path);


        if (str_starts_with($file_mime_type ?? '', 'image/')) {
            if (env('DASHSCOPE_API_KEY_4_OCR')) {
                return (new QwenOcrReader())->getText($path, $file_mime_type);
            } else {
                try {
                    $result = (new TesseractOCR($path))
                        ->lang('eng', 'chi_sim')
                        ->run(120);
                } catch (TesseractOcrException|Throwable $e) {
                    return false;
                }

                return is_string($result) ? $result : false;
            }
        }

        if ($fileExtension === 'pdf') {
            return Pdf::getText($path);
        }

        if ($fileExtension === 'docx') {
            $docxReader = new DocxReader();

            return $docxReader->getText($path);
        }

        return file_get_contents($path);
    }

    private function getDocument(string $content, string $entry): mixed
    {
        $document = new $this->documentClassName();
        $document->content = $content;
        $document->sourceType = $this->sourceType;
        $document->sourceName = $entry;
        $document->hash = \hash('sha256', $content);

        return $document;
    }

    private function validExtension(string $fileExtension): bool
    {
        if ($this->extensions === []) {
            return true;
        }

        return in_array($fileExtension, $this->extensions);
    }
}
