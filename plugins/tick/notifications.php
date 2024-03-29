<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class notifications
 */
class notifications {
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;
    /**
     * @var
     */
    var $nextCheck;
    /**
     * @var
     */
    var $keys;
    /**
     * @var
     */
    var $keyCount;
    /**
     * @var
     */
    var $toDiscordChannel;
    /**
     * @var
     */
    var $newestNotificationID;
    /**
     * @var
     */
    var $maxID;
    /**
     * @var
     */
    var $charApi;
    /**
     * @var
     */
    var $corpApi;
    /**
     * @var
     */
    var $alliApi;
    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->toDiscordChannel = $config["plugins"]["notifications"]["channelID"];
        $this->newestNotificationID = getPermCache("newestNotificationID");
        $this->maxID = 0;
        $this->keyCount = count($config["eve"]["apiKeys"]);
        $this->keys = $config["eve"]["apiKeys"];
        $this->nextCheck = 0;
        // Rena APIs
        $this->charApi = "http://rena.karbowiak.dk/api/character/information/";
        $this->corpApi = "http://rena.karbowiak.dk/api/corporation/information/";
        $this->alliApi = "http://rena.karbowiak.dk/api/alliance/information/";
        // Schedule all the apiKeys for the future
        $keyCounter = 0;
        foreach ($this->keys as $keyOwner => $apiData) {
            $keyID = $apiData["keyID"];
            $characterID = $apiData["characterID"];
            if ($keyCounter == 0) {
                // Schedule it for right now
                setPermCache("notificationCheck{$keyID}{$keyOwner}{$characterID}", time() - 5);
            }
            $keyCounter++;
        }
    }
    /**
     *
     */
    function tick()
    {
        $check = true;
        foreach ($this->keys as $keyOwner => $api) {
            if ($check == false) {
                            continue;
            }
            $keyID = $api["keyID"];
            $vCode = $api["vCode"];
            $characterID = $api["characterID"];
            $lastChecked = getPermCache("notificationCheck{$keyID}{$keyOwner}{$characterID}");
            if ($lastChecked <= time()) {
                $this->logger->info("Checking API Key {$keyID} belonging to {$keyOwner} for new notifications");
                $this->getNotifications($keyID, $vCode, $characterID);
                setPermCache("notificationCheck{$keyID}{$keyOwner}{$characterID}", time() + 1805); // Reschedule it's check for 30minutes from now (Plus 5s, ~CCP~)
                $check = false;
            }
        }
    }

    /**
     * @param $keyID
     * @param $vCode
     * @param $characterID
     * @return null
     */
    function getNotifications($keyID, $vCode, $characterID)
    {
        try {
            $url = "https://api.eveonline.com/char/Notifications.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}";
            $data = json_decode(json_encode(simplexml_load_string(downloadData($url), "SimpleXMLElement", LIBXML_NOCDATA)), true);
            $data = $data["result"]["rowset"]["row"];
            // If there is no data, just quit..
            if (empty($data)) {
                            return;
            }

            $fixedData = array();
            if (empty($data["@attributes"])) {
                return;
            }

            $fixedData[] = $data["@attributes"];
            if (count($data) > 1) {
                foreach ($data as $multiNotif) {
                                    $fixedData[] = $multiNotif["@attributes"];
                }
            }
            foreach ($fixedData as $notification) {
                $notificationID = $notification["notificationID"];
                $typeID = $notification["typeID"];
                $sentDate = $notification["sentDate"];
                if ($notificationID > $this->newestNotificationID) {
                    $notificationString = explode("\n", $this->getNotificationText($keyID, $vCode, $characterID, $notificationID));
                    switch ($typeID) {
                        case 5: // War Declared
                            $aggAllianceID = trim(explode(": ", $notificationString[2])[1]);
                            $aggAllianceName = $this->apiData("alli", $aggAllianceID)["allianceName"];
                            $delayHours = trim(explode(": ", $notificationString[3])[1]);
                            $msg = "War declared by {$aggAllianceName}. Fighting begins in roughly {$delayHours} hours.";
                            break;
                        case 8: // Alliance war invalidated by CONCORD
                            $aggAllianceID = trim(explode(": ", $notificationString[2])[1]);
                            $aggAllianceName = $this->apiData("alli", $aggAllianceID)["allianceName"];
                            $msg = "War declared by {$aggAllianceName} has been invalidated. Fighting ends in roughly 24 hours.";
                            break;
                        case 14: // Bounty payment
                            $msg = "skip";
                            break;
                        case 35: // Insurance payment
                            $msg = "skip";
                            break;
                        case 71: // Mission Expiration
                            $msg = "skip";
                            break;
                        case 75: // POS / POS Module under attack
                            $aggAllianceID = trim(explode(": ", $notificationString[0])[1]);
                            $aggAllianceName = $this->apiData("alli", $aggAllianceID)["allianceName"];
                            $aggCorpID = trim(explode(": ", $notificationString[1])[1]);
                            $aggCorpName = $this->apiData("corp", $aggCorpID)["corporationName"];
                            $aggID = trim(explode(": ", $notificationString[2])[1]);
                            $aggCharacterName = $this->apiData("char", $aggID)["characterName"];
                            $armorValue = trim(explode(": ", $notificationString[3])[1]);
                            $hullValue = trim(explode(": ", $notificationString[4])[1]);
                            $moonID = trim(explode(": ", $notificationString[5])[1]);
                            $moonName = dbQueryField("SELECT itemName FROM mapAllCelestials WHERE itemID = :id", "itemName", array(":id" => $moonID), "ccp");
                            $shieldValue = trim(explode(": ", $notificationString[6])[1]);
                            $solarSystemID = trim(explode(": ", $notificationString[7])[1]);
                            $typeID = trim(explode(": ", $notificationString[8])[1]);
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $typeID), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $solarSystemID), "ccp");
                            $msg = "{$typeName} under attack in **{$systemName} - {$moonName}** by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}). Status: Hull: {$hullValue}, Armor: {$armorValue}, Shield: {$shieldValue}";
                            break;
                        case 76: // Tower resource alert
                            $moonID = trim(explode(": ", $notificationString[2])[1]);
                            $moonName = dbQueryField("SELECT itemName FROM mapAllCelestials WHERE itemID = :id", "itemName", array(":id" => $moonID), "ccp");
                            $solarSystemID = trim(explode(": ", $notificationString[3])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $solarSystemID), "ccp");
                            $blocksRemaining = trim(explode(": ", $notificationString[6])[1]);
                            $typeID = trim(explode(": ", $notificationString[7])[1]);
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $typeID), "ccp");
                            $msg = "POS in {$systemName} - {$moonName} needs fuel. Only {$blocksRemaining} {$typeName}'s remaining.";
                            break;
                        case 88: // IHUB is being attacked
                            $aggAllianceID = trim(explode(": ", $notificationString[0])[1]);
                            $aggAllianceName = $this->apiData("alli", $aggAllianceID)["allianceName"];
                            $aggCorpID = trim(explode(": ", $notificationString[0])[1]);
                            $aggCorpName = $this->apiData("corp", $aggCorpID)["corporationName"];
                            $aggID = trim(explode(": ", $notificationString[1])[1]);
                            $aggCharacterName = $this->apiData("char", $aggID)["characterName"];
                            $armorValue = trim(explode(": ", $notificationString[3])[1]);
                            $hullValue = trim(explode(": ", $notificationString[4])[1]);
                            $shieldValue = trim(explode(": ", $notificationString[5])[1]);
                            $solarSystemID = trim(explode(": ", $notificationString[6])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $solarSystemID), "ccp");
                            $msg = "IHUB under attack in **{$systemName}** by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}). Status: Hull: {$hullValue}, Armor: {$armorValue}, Shield: {$shieldValue}";
                            break;
                        case 93: // Customs office is being attacked
                            $aggAllianceID = trim(explode(": ", $notificationString[0])[1]);
                            $aggAllianceName = $this->apiData("alli", $aggAllianceID)["allianceName"];
                            $aggCorpID = trim(explode(": ", $notificationString[0])[1]);
                            $aggCorpName = $this->apiData("corp", $aggCorpID)["corporationName"];
                            $aggID = trim(explode(": ", $notificationString[2])[1]);
                            $aggCharacterName = $this->apiData("char", $aggID)["characterName"];
                            $planetID = trim(explode(": ", $notificationString[3])[1]);
                            $planetName = dbQueryField("SELECT itemName FROM mapAllCelestials WHERE itemID = :id", "itemName", array(":id" => $planetID), "ccp");
                            $shieldValue = trim(explode(": ", $notificationString[5])[1]);
                            $solarSystemID = trim(explode(": ", $notificationString[6])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $solarSystemID), "ccp");
                            $msg = "Customs Office under attack in **{$systemName}** ($planetName) by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}). Shield Status: {$shieldValue}";
                            break;
                        case 94: // POCO Reinforced
                            $msg = "Customs Office reinforced.";
                            break;
                        case 138: // Clone activation
                            $msg = "skip";
                            break;
                        case 140: // Kill report
                            $msg = "skip";
                            break;
                        case 147: // Entosis has stated
                            $systemID = trim(explode(": ", $notificationString[0])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $systemID), "ccp");
                            $typeID = trim(explode(": ", $notificationString[1])[1]);
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $typeID), "ccp");
                            $msg = "Entosis has started in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 148: // Entosis enabled a module ??????
                            $systemID = trim(explode(": ", $notificationString[0])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $systemID), "ccp");
                            $typeID = trim(explode(": ", $notificationString[1])[1]);
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $typeID), "ccp");
                            $msg = "Entosis has enabled a module in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 149: // Entosis disabled a module
                            $systemID = trim(explode(": ", $notificationString[0])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $systemID), "ccp");
                            $typeID = trim(explode(": ", $notificationString[1])[1]);
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $typeID), "ccp");
                            $msg = "Entosis has disabled a module in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 160: // Entosis successful
                            $msg = "Hostile entosis successful. Structure has entered reinforced mode. (Unfortunately this api endpoint doesn't provide any more details)";
                            break;
                        case 161: //  Command Nodes Decloaking
                            $systemID = trim(explode(": ", $notificationString[2])[1]);
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $systemID), "ccp");
                            $msg = "Command nodes decloaking for **{$systemName}**";
                            break;
                    }

                    /** @noinspection PhpUndefinedVariableInspection */
                    if ($msg == "skip") {
                        return null;
                    }
                    $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                    // Find the maxID so we don't output this message again in the future
                    $this->maxID = max($notificationID, $this->maxID);
                    $this->newestNotificationID = $this->maxID;
                    setPermCache("newestNotificationID", $this->maxID);
                }
            }
        } catch (Exception $e) {
            $this->logger->info("Notification Error: " . $e->getMessage());
        }
    }
    /**
     * @param $keyID
     * @param $vCode
     * @param $characterID
     * @param $notificationID
     * @return string
     */
    function getNotificationText($keyID, $vCode, $characterID, $notificationID)
    {
        $url = "https://api.eveonline.com/char/NotificationTexts.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}&IDs={$notificationID}";
        $data = json_decode(json_encode(simplexml_load_string(downloadData($url), "SimpleXMLElement", LIBXML_NOCDATA)), true);
        $data = $data["result"]["rowset"]["row"];
        return $data;
    }
    /**
     *
     */
    function onMessage()
    {
    }

    /**
     * @param string $type
     * @param string $typeID
     * @return mixed
     */
    function apiData($type, $typeID) {
        $downloadFrom = "";
        switch ($type) {
            case "char":
                $downloadFrom = $this->charApi;
                break;
            case "corp":
                $downloadFrom = $this->corpApi;
                break;
            case "alli":
                $downloadFrom = $this->alliApi;
                break;
        }
        return json_decode(downloadData($downloadFrom . $typeID . "/"), true);
    }
    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(""),
            "information" => ""
        );
    }
}
