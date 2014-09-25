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

    /*
     * @return String
     */
    public function getProfileImageUrl()
    {
        $image = array_pop($this->bean->withCondition('image_type_id = ?', [1])->ownImage);
        if ($image) 
        {
            $image->setApp($this->app);
            return $image->getImageUrl();
        }
        else
            return "/public/images/avatar.png";

    }

    /*
     * @return String
     */
    public function getCoverImageUrl()
    {
        $image = array_pop($this->bean->withCondition('image_type_id = ?', [2])->ownImage);
        if ($image) 
        {
            $image->setApp($this->app);
            return $image->getImageUrl();
        }
        else
            return "/public/images/cover_picture.jpg";
    }

    /*
     * @return bool 
     */
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

        $queryString = implode(" AND ", $query);
            
        return (bool)$this->app->datasource->findOne('user',  $queryString, $params);
    }
}
