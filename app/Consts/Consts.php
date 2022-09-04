<?php

namespace App\Consts;

class Consts
{
    public const TEST = "1";
    public const API_SUCCESS = "200";

    public const API_FAILED_LOGIN = "99";
    public const API_FAILED_AUTH = "100";

    public const API_FAILED_PARAM = "300";

    public const API_FAILED_NODATA = "400";
    public const API_FAILED_DUPLICATE = "401";
    public const API_FAILED_MISMATCH = "402";
    public const API_FAILED_LIMIT = "403";

    public const API_FAILED_PRIVATE = "500";
    public const API_FAILED_FILE = "600";
    public const API_FAILED_PUBLISHING = "700";

    public const API_FAILED_EXEPTION = "900";

    public const REGEX_PASSWORD = "/^[a-z0-9#!?&$%+-]{0,32}$/i";

    public const CONVERSION1 = "#::::::::::::::::::::::::::#";
    public const CONVERSION2 = "#,,,,,,,,,,,,,,,,,,,,,,,,,,#";
    
    public const TWITTER_API_KEY = "lx1A8j00xDo41tFuSTtuguUwp";
    public const TWITTER_API_SECRET = "quirTXTN0SW2xUvVHrb50Ja9idNKlFCngfP30QiZ4QLkjyU8Av";

}
