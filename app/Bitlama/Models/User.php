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
        return $dateTime;
    }

    /*
     * @return DateTime
     */
    public function getRegisteredDateTime()
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($this->bean->registeredTimestamp);
        return $dateTime;
    }

    public function exists(array $filter)
    {
        $query = [];
        $params = [];
        if (isset($filter['alias']))
        {
            $query[] = 'alias = ?';
            $params[] = $filter['alias'];
        }
        else
            throw \InvalidArgument();

        $queryString = implode(" ", $query);
            
        return (bool)$this->app->datasource->findOne('user',  $queryString, $params);
    }
}
