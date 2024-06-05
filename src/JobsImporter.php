<?php

declare(strict_types=1);

final class JobsImporter
{
    private PDO $db;

    private string $file;

    public function __construct(string $host, string $username, string $password, string $databaseName, string $file)
    {
        $this->file = $file;
        
        /* connect to DB */
        try {
            $this->db = new PDO('mysql:host=' . $host . ';dbname=' . $databaseName, $username, $password);
        } catch (Exception $e) {
            die('DB error: ' . $e->getMessage() . "\n");
        }
    }

    public function importJobsXML(): int
    {
        /* remove existing items */
        $this->db->exec('DELETE FROM job');

        /* parse XML file */
        $xml = simplexml_load_file($this->file);

        /* import each item */
        $count = 0;
        foreach ($xml->item as $item) {
            $this->db->exec('INSERT INTO job (reference, title, description, url, company_name, publication) VALUES ('
                . '\'' . addslashes((string) $item->ref) . '\', '
                . '\'' . addslashes((string) $item->title) . '\', '
                . '\'' . addslashes((string) $item->description) . '\', '
                . '\'' . addslashes((string) $item->url) . '\', '
                . '\'' . addslashes((string) $item->company) . '\', '
                . '\'' . addslashes((string) $item->pubDate) . '\')'
            );
            $count++;
        }
        return $count;
    }

    public function importJobsJSON(): int
    {
        /* remove existing items */
        $this->db->exec('DELETE FROM job');

        /* parse JSON file */
        $json = json_decode(file_get_contents($this->file), true);

        $offerUrlPrefix = $json["offerUrlPrefix"];
        /* import each item */
        $count = 0;
        foreach ($json["offers"] as $item) {
            $date = (DateTime::createFromFormat('D M d H:i:s T Y', $item['publishedDate']))->format('Y-m-d H:i:s');
            $this->db->exec('INSERT INTO job (reference, title, description, url, company_name, publication) VALUES ('
                . '\'' . addslashes($item['reference']) . '\', '
                . '\'' . addslashes($item['title']) . '\', '
                . '\'' . addslashes($item['description']) . '\', '
                . '\'' . addslashes("{$offerUrlPrefix}{$item['urlPath']}") . '\', '
                . '\'' . addslashes($item['companyname']) . '\', '
                . '\'' . addslashes($date) . '\')'
            );
            $count++;
        }
        return $count;
    }
}
