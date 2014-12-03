<?php

// INTERACTIONS WITH SESSION STATE
// BATTLE STATIONS EVERYONE STUFF IT IS ABOUT TO GO DOWN!

namespace Bitlama\Auth;

class User {

    public static function isLoggedIn()
    {
        if (isset($_SESSION['user_id']))
            return true;
        else
            return false;
    } 

    // Return false if not set? Screw that. One should check isLoggedIn() before hand. #codepurity #experimental 
    public static function getUserId()
    {
        assert(self::isLoggedIn());

        return $_SESSION['user_id'];
    }

    public static function login($userId)
    {
        $_SESSION['user_id'] = $userId;
    }

    public static function logout()
    {
        unset($_SESSION);
    }
}
