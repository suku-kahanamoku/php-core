<?php

declare(strict_types=1);

namespace App\Modules\Templater;

use RuntimeException;

class TemplaterService
{
    private string $_emailsDir;
    private string $_code;

    public function __construct(string $franchiseCode = '')
    {
        $this->_emailsDir = dirname(__DIR__, 3) . '/emails/';
        $this->_code      = $franchiseCode;
    }

    /**
     * Renderuje PHP sablonovaci soubor a vraci HTML string.
     *
     * @param  string               $template  Cesta k sablone bez pripony, napr. 'test'
     * @param  array<string, mixed> $data      Promenne dostupne v sablone
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        // Nejdrive hledej ve franchise podslozce, pak fallback do korene
        $file = $this->_code !== ''
            ? $this->_emailsDir . $this->_code . '/' . $template . '.php'
            : '';

        if ($file === '' || !file_exists($file)) {
            $file = $this->_emailsDir . $template . '.php';
        }

        if (!file_exists($file)) {
            throw new RuntimeException("Sablona '{$template}' nebyla nalezena.");
        }

        extract($data, EXTR_SKIP);

        // $tpl je dostupny uvnitr sablony pro includovani partialu
        $tpl = $this;

        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
