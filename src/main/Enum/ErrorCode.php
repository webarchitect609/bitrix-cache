<?php

namespace WebArch\BitrixCache\Enum;

class ErrorCode
{
    const NEGATIVE_INTERVAL = 1;

    const PAST_EXPIRATION_TIME = 2;

    const EMPTY_PATH = 3;

    const PATH_DOES_NOT_START_WITH_SLASH = 4;

    const PATH_ENDS_WITH_SLASH = 5;

    const EMPTY_KEY = 6;

    const EMPTY_BASE_DIR = 7;

    const BASE_DIR_STARTS_OR_ENDS_WITH_SLASH = 8;

    const NEGATIVE_OR_ZERO_TTL = 9;

    const INVALID_TTL_TYPE = 10;

    const KEYS_IS_NOT_ARRAY = 11;

    const ERROR_REFLECTING_CALLBACK = 12;

    const CALLBACK_THROWS_EXCEPTION = 13;

    const CALLBACK_CANNOT_FIND_CACHED_VALUE_IN_VARS = 14;

    const ERROR_OBTAINING_BITRIX_CACHE_INSTANCE = 15;

    const ERROR_OBTAINING_BITRIX_TAGGED_CACHE_INSTANCE = 16;

    const EMPTY_TAG = 17;

    const INVALID_IBLOCK_ID = 18;

    const INVALID_BETA = 19;

    const INVALID_EXPIRATION = 20;

    const INVALID_EXPIRATION_DATE = 21;

    const NON_TAG_AWARE = 22;

    const INVALID_TAG = 23;

    const RESERVED_CHARACTERS_IN_TAG = 24;

    const INVALID_KEY_TYPE = 26;

    const RESERVED_CHARACTERS_IN_KEY = 27;
}
