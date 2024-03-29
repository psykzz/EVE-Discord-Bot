<?php
/**
 * @param $url
 * @return mixed|null
 */
function downloadData($url)
{
    try
    {
        $userAgent = "Discord bot";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        $headers = array();
        $headers[] = "Connection: keep-alive";
        $headers[] = "Keep-Alive: timeout=10, max=1000";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        return $result;
    }
    catch(Exception $e)
    {
        var_dump("cURL Error: " . $e->getMessage());
        return null;
    }
}
/**
 * @param $url
 * @param $downloadPath
 * @return bool
 */
function downloadLargeData($url, $downloadPath)
{
    try {
        $readHandle = fopen($url, "rb");
        $writeHandle = fopen($downloadPath, "w+b");
        if(!$readHandle || !$writeHandle)
            return false;
        while(!feof($readHandle)) {
            if(fwrite($writeHandle, fread($readHandle, 4096)) == FALSE)
                return false;
        }
        fclose($readHandle);
        fclose($writeHandle);
        return true;
    } catch (Exception $e) {
        var_dump("Download Error: " . $e->getMessage());
        return false;
    }
}
