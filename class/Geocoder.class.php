<?php

/**
 * Geocoder Class
 *
 */
Class Geocoder
{
    // Google maps api key. (optional, good for tracking)
    private $apiKey = '';

    /**
     * Get url by curl.
     * @param $url
     * @return mixed
     */
    private function curl($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_HEADER, 0); // Don't return headers.
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $data = curl_exec($ch);

        curl_close($ch);

        return $data;
    }

    /**
     * Reverse geocode by lat and lang using Google Maps API.
     * @param $lat
     * @param $lng
     * @return object
     */
    private function reverseGoogle($lat, $lng)
    {
        // Get urls content with curl.
        $data = $this->curl("https://maps.google.com/maps/api/geocode/json?latlng={$lat},{$lng}&language=de&key={$this->apiKey}");

        // Decode json.
        $jsonData = json_decode($data);

        return $jsonData;
    }

    /**
     * Reverse geocode by lat and lang using Nominatim API.
     * @param $lat
     * @param $lng
     * @return object
     */
    private function reverseNominatim($lat, $lng)
    {
        // Get urls content with curl.
        $data = $this->curl("http://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&accept-language=de");

        // Decode json.
        $jsonData = json_decode($data);

        return $jsonData;
    }

    /**
     * Get location by lat and lng.
     * @param $lat
     * @param $lng
     * @return array
     */
    public function getLocation($lat, $lng)
    {
        // Init defaults.
        $location = array();
        $location['street'] = '';
        $location['district'] = '';

        // Get response object from reverse method using Google Maps API.
        $data = $this->reverseGoogle($lat, $lng);

        // Received valid data from Google.
        if (!empty($data) && !empty($data->status) && $data->status == 'OK' && !empty($data->results)) {

            // Init vars.
            $locality = '';
            $sublocalityLv2 = '';
            $sublocality = '';

            // Iterate each result.
            foreach ($data->results as $result) {

                // Check for address components.
                if (!empty($result->address_components)) {
                    // Iterate each address component.
                    foreach ($result->address_components as $address_component) {

                        // Street found.
                        if (in_array('route', $address_component->types) && !empty($address_component->long_name)) {
                            // Set street by first found.
                            $location['street'] = empty($location['street']) ? $address_component->long_name : $location['street'];
                        }

                        // Sublocality level2 found.
                        if (in_array('sublocality_level_2', $address_component->types) && !empty($address_component->long_name)) {
                            // Set sublocality level 2 by first found.
                            $sublocalityLv2 = empty($sublocalityLv2) ? $address_component->long_name : $sublocalityLv2;
                        }

                        // Sublocality found.
                        if (in_array('sublocality', $address_component->types) && !empty($address_component->long_name)) {
                            // Set sublocality by first found.
                            $sublocality = empty($sublocality) ? $address_component->long_name : $sublocality;
                        }

                        // Locality found.
                        if (in_array('locality', $address_component->types) && !empty($address_component->long_name)) {
                            // Set sublocality by first found.
                            $locality = empty($sublocality) ? $address_component->long_name : $sublocality;
                        }
                        break;
                    }
                }
            }

            // Set district by priority.
            if (!empty($sublocalityLv2)) {
                $location['district'] = $sublocalityLv2;

            } else if ($sublocality) {
                $location['district'] = $sublocality;

            } else if ($locality) {
                $location['district'] = $locality;
            }

            // Rename street responses.
            switch ($location['street']) {
                case 'Unnamed Road':
                    $location['street'] = 'Irgendwo im Wald';
                    break;
            }

        // Use backup API when getting an invalid response. (Obviously limit exceeded)
        } else {
            // Get response object from reverse method using Nominatim API.
            $data = $this->reverseNominatim($lat, $lng);

            // Valid response.
            if (!empty($data) && !empty($data->address)) {

                // Try to get road.
                if (!empty($data->address->road)) {
                    $location['street'] = $data->address->road;

                // Otherwise try to get cycleway.
                } else if (!empty($data->address->cycleway)) {
                    $location['street'] = $data->address->cycleway;

                // Otherwise try to get building.
                } else if (!empty($data->address->building)) {
                    $location['street'] = $data->address->building;
                }

                // Try to get suburb.
                if (!empty($data->address->suburb)) {
                    $location['district'] = $data->address->suburb;

                // Otherwise try to get city district.
                } else if (!empty($data->address->city_district)) {
                    $location['district'] = $data->address->city_district;
                }
            }
        }

        // Return the location array.
        return $location;
    }
}