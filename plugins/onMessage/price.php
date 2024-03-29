<?php

/**
 * Class priceChecks
 */
class price
{
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
    var $solarSystems;
    /**
     * @var array
     */
    var $triggers = array();

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
        $systems = dbQuery("SELECT solarSystemName, solarSystemID FROM mapSolarSystems", array(), "ccp");
        foreach ($systems as $system) {
            $this->solarSystems[strtolower($system["solarSystemName"])] = $system["solarSystemID"];
            $this->triggers[] = "!" . strtolower($system["solarSystemName"]);
        }
        $this->triggers[] = "!pc";
            $this->excludeChannel = $config["plugins"]["priceChecker"]["channelID"];
    }

    /**
     *
     */
    function tick()
    {

    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
        // Bind a few things to vars for the plugins
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];

        // Quick Lookups
        $quickLookUps = array(
            "plex" => array(
                "typeID" => 29668,
                "typeName" => "30 Day Pilot's License Extension (PLEX)"
            ),
            "30 day" => array(
                "typeID" => 29668,
                "typeName" => "30 Day Pilot's License Extension (PLEX)"
            )
        );

        $data = command(strtolower($message), $this->information()["trigger"]);
        if (isset($data["trigger"])) {

            $systemName = $data["trigger"];
            $itemName = $data["messageString"];

            $single = dbQueryRow("SELECT typeID, typeName FROM invTypes WHERE typeName = :item COLLATE NOCASE", array(":item" => ucfirst($itemName)), "ccp");
            $multiple = dbQuery("SELECT typeID, typeName FROM invTypes WHERE typeName LIKE :item COLLATE NOCASE LIMIT 5", array(":item" => "%" . ucfirst($itemName) . "%"), "ccp");

            // Quick lookups
            if (isset($quickLookUps[$itemName])) {
                            $single = $quickLookUps[$itemName];
            }

            // Sometimes the multiple lookup is returning just one
            if (count($multiple) == 1) {
                            $single = $multiple[0];
            }

            // Check if the channel is restricted    
            if ($channelID == $this->excludeChannel) {
                            return $this->discord->api("channel")->messages()->create($channelID, "**Price Check not allowed in this channel**");
            }

            // If there are multiple results, and not a single result, it's an error
            if (empty($single) && !empty($multiple)) {
                $items = array();
                foreach ($multiple as $item) {
                                    $items[] = $item["typeName"];
                }

                $items = implode(", ", $items);
                return $this->discord->api("channel")->messages()->create($channelID, "**Multiple results found:** {$items}");
            }

            // If there is a single result, we'll get data now!
            if ($single) {
                $typeID = $single["typeID"];
                $typeName = $single["typeName"];

                $solarSystemID = $systemName == "pc" ? "global" : $this->solarSystems[$systemName];

                // Get pricing data
                if ($solarSystemID == "global") {
                                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?typeid={$typeID}"));
                } else {
                                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?usesystem={$solarSystemID}&typeid={$typeID}"));
                }

                $lowBuy = number_format((float) $data->marketstat->type->buy->min, 2);
                $avgBuy = number_format((float) $data->marketstat->type->buy->avg, 2);
                $highBuy = number_format((float) $data->marketstat->type->buy->max, 2);
                $lowSell = number_format((float) $data->marketstat->type->sell->min, 2);
                $avgSell = number_format((float) $data->marketstat->type->sell->avg, 2);
                $highSell = number_format((float) $data->marketstat->type->sell->max, 2);

                $this->logger->info("Sending pricing info to {$channelName} on {$guildName}");
                $solarSystemName = $systemName == "pc" ? "Global" : ucfirst($systemName);
                $messageData = "**System: {$solarSystemName}**
**Buy:**
   Low: {$lowBuy}
   Avg: {$avgBuy}
   High: {$highBuy}
**Sell:**
   Low: {$lowSell}
   Avg: {$avgSell}
   High: {$highSell}";
                $this->discord->api("channel")->messages()->create($channelID, $messageData);
            } else {
                $this->discord->api("channel")->messages()->create($channelID, "**Error:** ***{$itemName}*** not found");
            }
        }
        return null;
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "pc",
            "trigger" => $this->triggers,
            "information" => "Shows price information for items in EVE. To use simply type !pc <item_name> or !<system_name> <item_name>"
        );
    }
}
