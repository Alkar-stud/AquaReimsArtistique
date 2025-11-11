<?php
// Fichier: app/Services/Csv/BaseCsv.php
namespace app\Services\Csv;

class BaseCsv
{
    private string $delimiter = ';';
    private string $enclosure = '"';
    private string $escape = '\\';

    /**
     * Génère le contenu CSV (UTF\-8 BOM) en mémoire et le retourne.
     *
     * @param array $headerFields
     * @param array $rows
     * @return string
     */
    public function generateContent(array $headerFields, array $rows): string
    {
        $columns = $this->normalizeColumns($headerFields);
        $titles  = $this->normalizeHeaderTitles($headerFields);

        $fp = fopen('php://temp', 'r+');

        // BOM
        fwrite($fp, "\xEF\xBB\xBF");

        // Entêtes
        fputcsv($fp, $titles, $this->delimiter, $this->enclosure, $this->escape);

        // Lignes
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $line[] = $value;
            }
            fputcsv($fp, $line, $this->delimiter, $this->enclosure, $this->escape);
        }

        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);
        return $content;
    }

    private function normalizeColumns(array $headerFields): array
    {
        $cols = [];
        foreach ($headerFields as $hf) {
            if (is_string($hf)) {
                $cols[] = $hf;
            } elseif (is_array($hf) && isset($hf['value'])) {
                $cols[] = (string)$hf['value'];
            } elseif (is_array($hf) && isset($hf['key'])) {
                $cols[] = (string)$hf['key'];
            }
        }
        return $cols;
    }

    private function normalizeHeaderTitles(array $headerFields): array
    {
        $titles = [];
        foreach ($headerFields as $hf) {
            if (is_string($hf)) {
                $titles[] = $hf;
            } elseif (is_array($hf) && isset($hf['label'])) {
                $titles[] = (string)$hf['label'];
            } elseif (is_array($hf) && isset($hf['value'])) {
                $titles[] = (string)$hf['value'];
            } elseif (is_array($hf) && isset($hf['key'])) {
                $titles[] = (string)$hf['key'];
            }
        }
        return $titles;
    }
}
