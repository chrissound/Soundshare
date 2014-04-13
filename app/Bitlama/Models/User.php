<?php
namespace Bitlama\Models;

class User extends \Bitlama\Models\BaseModel {

    /*
     * @return DateTime
     */
    public function getLoginDateTime()
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($this->bean->loginTimestamp);
        el($dateTime);
        return $dateTime;
    }

    /*
     * @return DateTime
     */
    public function getRegisteredDateTime()
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($this->bean->registeredTimestamp);
        el($dateTime);
        return $dateTime;
    }
}
