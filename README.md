- A spider use by php about sports(only for football and basketball) match video or live in Chinese popular website(such like Tencent,PPTV,CCTV,Zhibo8,Wuchajian).

- Install: Composer instal the packagist in composer.json,two tools are required.

    - phpquery = https://github.com/TobiaszCudnik/phpquery
    - QueryList = https://github.com/jae-jae/QueryList

- Usage: Is in /src/example.php.

- Result: Data such like /sql/example_data.sql, the video tag all in 'football_league_matchs'/'basketball_league_matchs' 'Video' field, as json format.

- Notice:
    - 1.In /src/SportsVideoSpider.php, the model use on thinkphp5,you must change model as your framework ORM.
    - 2.The php version must >= 5.4
    - 3.This repositorie is only used for technical open source, for the reptilian enthusiasts research.Do not use the repositorie for illegal commercial activities, otherwise please bear the responsibility, I hereby declare that I do not assume any resulting legal responsibility.

[![LICENSE](https://img.shields.io/badge/license-NPL%20(The%20996%20Prohibited%20License)-blue.svg)](https://github.com/996icu/996.ICU/blob/master/LICENSE)
[![996.icu](https://img.shields.io/badge/link-996.icu-red.svg)](https://996.icu)

