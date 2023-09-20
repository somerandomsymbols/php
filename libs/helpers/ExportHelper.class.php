<?php
class ExportHelper
{
    public static function createId(Model $model)
    {
        if ($model->validate())
        {
            $model->CreateRow();

            return $model->getLastRow();
        }
        else
            return false;
    }

    public static function exportData($model, &$data)
    {
        if (is_array($model))
            foreach ($model as $field => $value)
                if (is_array($value))
                    $data[$field] = reset($value);
                else
                    $data[$field] = $value;

        return is_array($model);
    }

    public static function importData(Model $model, array $data)
    {
        foreach ($model->GetFields() as $field)
            if (isset($data[$field]))
                $model->setFieldValue($field, addslashes($data[$field]));
    }

    public static function clearDirectory($path)
    {
        if (!is_dir($path))
            mkdir($path, 0777, true);
        else
            foreach (scandir($path = realpath($path)) as $file)
                if ($file != '.' && $file != '..')
                    if (is_dir($file = "$path/$file"))
                    {
                        self::clearDirectory($file);
                        rmdir($file);
                    }
                    else
                        unlink($file);
    }

    public static function toPhpPath($uri)
    {
        return $_SERVER['DOCUMENT_ROOT'] . $uri;
    }

    private static function saveDomHtml(DOMDocument $dom)
    {
        return str_replace(array('<html>','</html>','<body>','</body>'), '', $dom->saveHTML());
    }

    private static function &loadDomHtml($html)
    {
        $dom = new DOMDocument('4.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $removedNodes = array();

        foreach ($dom->childNodes as $node)
            if ($node->nodeType != XML_ELEMENT_NODE)
                $removedNodes[] = $node;

        foreach ($removedNodes as $node)
            $dom->removeChild($node);

        return $dom;
    }

    private static function getUrl($path, $reqUri)
    {
        $url = 'http';

        if ($_SERVER['HTTPS'] == 'on')
            $url .= 's';

        $url .= "://{$_SERVER['HTTP_HOST']}";

        if (substr($path, 0, 1) != '/')
            $url .= "$reqUri/";

        $url .= $path;

        return $url;
    }

    private static function getContentExt($file)
    {
        if ($mime = mime_content_type($file)
            and substr($mime, 0, 6) == 'image/')
            return substr($mime, 6);
        else
            return false;
    }

    public static function downloadFile($srcUrl, $dstPath)
    {
        $res = false;

        if ($curlHandle = curl_init()
            and curl_setopt($curlHandle, CURLOPT_URL, $srcUrl)
            and curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true)
            and $fileContent = curl_exec($curlHandle)
            and !curl_errno($curlHandle)
            and curl_getinfo($curlHandle, CURLINFO_HTTP_CODE) == 200
            and file_put_contents($dstPath, $fileContent))
            $res = true;

        curl_close($curlHandle);

        return $res;
    }

    public static function saveLinks($html, $uri, ZipArchive $zip)
    {
        $dom = self::loadDomHtml($html);

        foreach ($dom->getElementsByTagName('img') as $element)
            if (!filter_var($imgSrc = $element->getAttribute('src'), FILTER_VALIDATE_URL))
            {
                $tmpPath = sys_get_temp_dir() . '/' . uniqid();
                $srcUrl = self::getUrl($imgSrc, $uri);
                $dwlPath = "$tmpPath/download.file";

                if (!is_dir(dirname($dwlPath)))
                    mkdir(dirname($dwlPath), 0777, true);

                if (file_exists($dwlPath))
                    unlink($dwlPath);

                if (self::downloadFile($srcUrl, $dwlPath)
                    and $fileExt = self::getContentExt($dwlPath)
                    and $dstPath = "img$zip->numFiles.$fileExt"
                    and $zip->addFile($dwlPath, $dstPath))
                    $element->setAttribute('src', $dstPath);
                else
                    return false;
            }

        return self::saveDomHtml($dom);
    }

    public static function loadLinks($html, $srcPath, $dstUri)
    {
        $srcPath = realpath($srcPath);
        $dstPath = self::toPhpPath($dstUri);
        $dom = self::loadDomHtml($html);

        if (!is_dir($dstPath))
            mkdir($dstPath, 0777, true);

        foreach ($dom->getElementsByTagName('img') as $element)
            if (!filter_var($srcName = $element->getAttribute('src'), FILTER_VALIDATE_URL))
            {
                $dstName = 'img' . count(scandir($dstPath)) . '.' . pathinfo($srcName, PATHINFO_EXTENSION);

                copy("$srcPath/$srcName", "$dstPath/$dstName");
                $element->setAttribute('src', "$dstUri/$dstName");
            }

        return self::saveDomHtml($dom);
    }
}
