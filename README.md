- A spider use by php about sports(most for football and basketball) match video or live in Chinese website(such as tencent,pptv,cctv,zhibo8,wuchajian).

- Install: Composer instal the packagist in composer.json,two tools is required.

    - phpquery = https://github.com/TobiaszCudnik/phpquery
    - QueryList = https://github.com/jae-jae/QueryList

- Usage: Is in /src/example.php.

- Result: Data such like /sql/example_data.sql, the video tag all in 'football_league_matchs'/'basketball_league_matchs' 'Video' field, as json format.

- Notice:
    - 1.In /src/SportsVideoSpider.php, the model use on thinkphp5,you must change model as your framework ORM.
    - 2.The php version must > 5.4
    - 3.This repositorie is only used for technical open source, for the reptilian enthusiasts research.Do not use the repositorie for illegal commercial activities, otherwise please bear the responsibility, I hereby declare that I do not assume any resulting legal responsibility.

