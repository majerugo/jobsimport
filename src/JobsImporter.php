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

    function isJson($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    
    function isXml($string) {
        /* supress warnings */
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($string);
        return $xml !== false;
    }

    public function importJobs() : int
    {
        /* check if file is JSON or XML */
        if ($this->isJson(file_get_contents($this->file)))
        {
            return $this->importJobsJSON();
        }
        else if ($this->isXml(file_get_contents($this->file)))
        {
            return $this->importJobsXML();
        }
        else
        {
            die('File is not a valid JSON or XML file');
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
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            throw new Exception('JSON error: failed to parse JSON file.');
        }

        /* get offerUrlPrefix */
        $offerUrlPrefix = $json["offerUrlPrefix"];
        if ($offerUrlPrefix === null)
        {
            throw new Exception('JSON error: missing offerUrlPrefix.');
        }

        /* prepare request (to avoid SQL inject) */
        $req = $this->db->prepare('INSERT INTO job (reference, title, description, url, company_name, publication) VALUES (?, ?, ?, ?, ?, ?)');

        /* import each item */
        $count = 0;
        foreach ($json["offers"] as $item) {
            /* check fields */
            if (isset($item['reference'], $item['title'], $item['description'], $item['urlPath'], $item['companyname'], $item['publishedDate']) === false)
            {
                throw new Exception('JSON error: missing fields in item.');
            }
            
            /* format date */
            $date = (DateTime::createFromFormat('D M d H:i:s T Y', $item['publishedDate']))->format('Y-m-d H:i:s');
            if ($date === false)
            {
                throw new Exception('JSON error: failed to parse date.');
            }

            /* execute the request */
            $req->execute([
                addslashes($item['reference']),
                addslashes($item['title']),
                addslashes($item['description']),
                addslashes("{$offerUrlPrefix}{$item['urlPath']}"),
                addslashes($item['companyname']),
                addslashes($date)
            ]);
            $count++;
        }
        return $count;
    }
}
