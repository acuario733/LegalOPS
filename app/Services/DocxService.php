<?php

declare(strict_types=1);

namespace App\Services;

final class DocxService
{
    public function fromText(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [''];
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $escaped = htmlspecialchars(trim($paragraph), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $body .= '<w:p><w:r><w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
        }
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                . '</Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>' . $body . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr></w:body></w:document>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ];

        return $this->zip($files);
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $data = '';
        $central = '';
        $offset = 0;
        foreach ($files as $name => $contents) {
            $name = str_replace('\\', '/', $name);
            $compressed = gzdeflate($contents, 6);
            $crc = crc32($contents);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, strlen($compressed), strlen($contents), strlen($name), 0)
                . $name . $compressed;
            $data .= $local;
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                8,
                0,
                0,
                $crc,
                strlen($compressed),
                strlen($contents),
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name;
            $offset += strlen($local);
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($data), 0);

        return $data . $central . $end;
    }
}
