<?php
include('SportsVideoSpider.php');

// 1.足球比赛视频
if (date('H') < 4) {
    SportsVideoSpider::getFootballMatchsVideo(date('Y-m-d', (time() - 3600 * 24)));  // 昨天深夜
    SportsVideoSpider::getFootballMatchsVideo(date('Y-m-d'));  // 今天凌晨
} else {
    SportsVideoSpider::getFootballMatchsVideo(date('Y-m-d'));  // 今天的
}

// 2.篮球比赛视频
SportsVideoSpider::getBasketballMatchsVideo(date('Y-m-d'));    // 今天的

// 3.足球直播链接
$columnIdArr = [8, 21, 23, 208, 605, 5, 6, 22, 24, 4, 941];
for ($i = 1; $i <= 20; $i++) {  // 往后20天的
    foreach ($columnIdArr as $columnIdOne) {  // 要抓取的联赛
        SportsVideoSpider::getTencentFootballMatchsLive(date('Y-m-d', (time() + (86400 * $i))), "" . $columnIdOne);
    }

    SportsVideoSpider::getPPTVFootballMatchsLive(date('Y-m-d', (time() + (86400 * $i))), $i);
    SportsVideoSpider::getCCTVFootballMatchsLive(date('Y-m-d', (time() + (86400 * $i))));
    // SportsVideoSpider::getCCTVPlusFootballMatchsLive(date('Y-m-d', (time() + (86400 * $i))));
}

// 4.篮球直播链接
for ($i = 1; $i <= 20; $i++) {  // 往后五天的 nba
    SportsVideoSpider::getTencentBasketballMatchsLive(date('Y-m-d', (time() + (86400 * $i))), '100000');
    SportsVideoSpider::getCCTVBasketballMatchsLive(date('Y-m-d', (time() + (86400 * $i))));
}

for ($i = 1; $i <= 20; $i++) {  // 往后五天的 cba
    SportsVideoSpider::getTencentBasketballMatchsLive(date('Y-m-d', (time() + (86400 * $i))), '100008');
    SportsVideoSpider::getCCTVBasketballMatchsLive(date('Y-m-d', (time() + (86400 * $i))));
}

// 5.无插件网的直播链接
SportsVideoSpider::getWuChaJianLive();


