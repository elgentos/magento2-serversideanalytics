<?php

namespace Elgentos\ServerSideAnalytics\Service;

class UserDataProvider
{
    private ?array $sha256_email_address = [];
    private ?array $sha256_phone_number = [];

    private array $addresses = [];


    /**
     *  Hashed and encoded email address of the user. Normalized as such:
     *  - lowercase
     *  - remove periods before @ for gmail.com/googlemail.com addresses
     *  - remove all spaces
     *  - hash using SHA256 algorithm
     *  - encode with hex string format.
     *
     * @param string $email
     *
     * @return bool
     */
    public function setEmail(string $email): bool
    {
        $email = str_replace(" ", "", mb_strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return false;
        }

        // https://developers.google.com/analytics/devguides/collection/ga4/uid-data
        if (
            substr($email, -mb_strlen("@gmail.com")) == "@gmail.com" ||
            substr($email, -mb_strlen("@googlemail.com")) == "@googlemail.com"
        ) {
            [$addr, $host] = explode("@", $email, 2);

            if ($host == "googlemail.com") {
                $host = "gmail.com";
            }

            $addr = explode("+", $addr, 2)[0];
            $addr = str_replace(".", "", $addr);
            $email = implode("@", [trim($addr), trim($host)]);
        }

        $this->sha256_email_address[] = hash("sha256", $email);

        return true;
    }

    /**
     *  Hashed and encoded phone number of the user. Normalized as such:
     *  - remove all non digit characters
     *  - add + prefix
     *  - hash using SHA256 algorithm
     *  - encode with hex string format.
     *
     * @param int $number
     *
     * @return bool
     */
    public function setPhoneNumber(string $phoneNumber): bool
    {
        if (strlen($phoneNumber) < 3 || strlen($phoneNumber) > 15) {
            return false;
        }

        if (strpos($phoneNumber, '+') === 0) {
            $phoneNumber = "{$phoneNumber}";
        }else{
            $phoneNumber = "+{$phoneNumber}";
        }

        $this->sha256_phone_number[] = hash("sha256", $phoneNumber);

        return true;
    }


    public function addAddress(?string $firstName = null, ?string $lastName = null, ?string $street = null, ?string $city = null, ?string $region = null, ?string $postalCode = null, ?string $countryCode = null){
        $addressData = [];

        /**
         * Hashed and encoded first name of the user. Normalized as such:
         * - remove digits and symbol characters
         * - lowercase
         * - remove leading and trailing spaces
         * - hash using SHA256 algorithm
         * - encode with hex string format.
         */
        if (!empty($firstName)) {
            $addressData['sha256_first_name'] = hash("sha256", $this->strip($firstName, true));
        }

        /**
         * Hashed and encoded last name of the user. Normalized as such:
         * - remove digits and symbol characters
         * - lowercase
         * - remove leading and trailing spaces
         * - hash using SHA256 algorithm
         * - encode with hex string format.
         */
        if (!empty($lastName)){
            $addressData['sha256_last_name'] = hash("sha256", $this->strip($lastName, true));
        }

        /**
         * Hashed and encoded street and number of the user. Normalized as such:
         * - remove symbol characters
         * - lowercase
         * - remove leading and trailing spaces
         * - hash using SHA256 algorithm
         * - encode with hex string format.
         */
        if (!empty($street)){
            $addressData['sha256_street'] = hash("sha256", $this->strip($street));
        }

        /**
         * City for the address of the user. Normalized as such:
         * - remove digits and symbol characters
         * - lowercase
         * - remove leading and trailing spaces.
         */
        if (!empty($city)){
            $addressData['city'] = $this->strip($city, true);
        }

        /**
         * State or territory for the address of the user. Normalized as such:
         * - remove digits and symbol characters
         * - lowercase
         * - remove leading and trailing spaces.
         */
        if (!empty($region)) {
            $addressData['region'] = $this->strip($region, true);
        }

        /**
         * Postal code for the address of the user. Normalized as such:
         * - remove . and ~ characters
         * - remove leading and trailing spaces.
         */
        if (!empty($postalCode)) {
            $addressData['postal_code'] = $this->strip($postalCode);
        }

        if (!empty($countryCode)) {
            $addressData['country'] = mb_strtoupper(trim($countryCode));
        }

        $this->addresses[] = $addressData;
    }

    public function toArray(): array
    {
        $data = [];

        if (!empty($this->sha256_email_address)) {
            $data["sha256_email_address"] = $this->sha256_email_address;
        }

        if (!empty($this->sha256_phone_number)) {
            $data["sha256_phone_number"] = $this->sha256_phone_number;
        }

        $data['address'] = $this->addresses;

        return $data;
    }

    /**
     * @param string $string
     * @param bool $removeDigits
     * @return string
     */
    private function strip(string $string, bool $removeDigits = false): string
    {
        $replaceDecimals = $removeDigits ? '0-9' : '';

        $string = preg_replace("[^a-zA-Z{$replaceDecimals}\-\_\.\,\s]", "", $string);
        $string = mb_strtolower($string);

        return trim($string);
    }
}
