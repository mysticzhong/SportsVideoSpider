<?php
use model\FootballLeagueMatchs;
use model\BasketballLeagueMatchs;
use QL\QueryList;
use phpQuery\phpQuery;

/**
 * Class SportsVideoSpider
 * @author MysticZhong
 */
class SportsVideoSpider{

    public static function HttpSinaWeiBoVideo($url){
        // echo '<meta http-equiv="Content-type" content="text/html; charset=utf-8"/>';
        header("Content-type: text/html; charset=utf-8");

        $html = self::toHttpSinaWeiBoVideo($url);
        if(!$html){
            return false;
        }

        // vendor("phpQuery.phpQuery");
        $doc = phpQuery::newDocumentHTML($html,'utf-8');
        phpQuery::selectDocument($doc);

        foreach (pq('div.info_txt') as $v1) {  // 获取视频标题
            $title = mb_convert_encoding($v1->textContent,'ISO-8859-1','utf-8');
        }

        foreach (pq('div') as $div) {
            $node_type = $div->getAttribute('node-type');  // div里面的唯一属性
            $action_type = $div->getAttribute('action-type');  // 进一步校验

            if(
                $node_type == "common_video_player" &&
                $action_type == "feed_list_third_rend"
            ){
                $action_data = $div->getAttribute('action-data');  // 解析video的信息
                $action_data_arr = explode("&",$action_data);
                $param = [];
                foreach ($action_data_arr as $v){  // 组合为数组
                      $v2 = explode("=",$v);
                      if(
                          $v2[0] == "fnick" ||
                          $v2[0] == "video_src" ||
                          $v2[0] == "short_url" ||
                          $v2[0] == "cover_img"
                      ){
                          $param[$v2[0]] = urldecode($v2[1]);
                      }else{
                          $param[$v2[0]] = $v2[1];
                      }

                }

                 unset($param['play_count']);
                 $param['title'] = $title;
                 $param['video_src'] = "http:".$param['video_src'];
                 parse_str(parse_url($param['video_src'])['query'],$upa);

            }

        }

        // 组成资源表插入数组
        $vs = [];
        $vs['OriginWebUrl'] = $url;
        $vs['Title'] = mb_substr($title,0,30,'utf-8');
        $vs['CoverImg'] = $param['cover_img'];
        $vs['Height'] = $param['card_height'];
        $vs['Weight'] = $param['card_width'];
        $vs['TimeLength'] = 0;
        $vs['FileSize'] = 0;
        $vs['Expires'] = $upa['Expires'];
        $vs['VideoSrc'] = $param['video_src'];
        $vs['Bitrate'] = $param['bitrate'];
        return $vs;
    }


    /**
     * 抓取weibo.com/tv/v/信息标签
     */
    private static function toHttpSinaWeiBoVideo($url){
        // 伪装useragent
        $UA = "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Safari/537.36";

        // 伪装请求头
        $header = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
            "Accept-Encoding" => "gzip, deflate, br",
            "Accept-Language" => "zh-CN,zh;q=0.9",
            "Cache-Control" => "max-age=0",
            "Connection" => "keep-alive",
            "Host" => "weibo.com",
            "Upgrade-Insecure-Requests" => 1,
            "User-Agent" => $UA
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_HEADER, 0);            // 获取头部信息 debug
        curl_setopt($ch, CURLOPT_USERAGENT, $UA);

        // 设置伪装cookie 以后设置为读缓存
        curl_setopt($ch, CURLOPT_COOKIE, "TC-Page-G0=6fdca7ba258605061f331acb73120318; SUB=_2AkMtOSWwf8NxqwJRmP8dymrkZY92zQ7EieKbZdRrJRMxHRl-yT9jqmgGtRB6BrkLX5HQh96904-g0o3I0md3hTuX9QLb; SUBP=0033WrSXqPxfM72-Ws9jqgMF55529P9D9WhdQAmwYWGnq8CdinR49wi.");
        // 设置保存cookie文件
        // curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam('/data_other/sina/','cookie'));
        curl_setopt($ch, CURLOPT_URL, $url);

        $content = curl_exec($ch);
        // echo '真正页面内容';
        // echo "\r\n\r\n\r\n\r\n\r\n";

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        //返回结果
        if ($content && $httpcode == 200) {
            return $content;
        } else {
            // $error = curl_errno($ch);
            return null;
        }

    }

    /**
     * @desc 抓取足球比赛视频
     */
    public static function getFootballMatchsVideo($date){
        $keyName1 = '集锦';      // 标签名1
        $keyName2 = '录像';      // 标签名2
        $keyName3 = '腾讯直播';  // 标签名3

        $gameList = FootballLeagueMatchs::where(' LeagueID in (2,3,5,4,6,7,1,23,15,49,97) ')
            ->where(' StartTime > "' . date('Y-m-d 00:00:00', (time() - 3600 * 24 * 2)) . '"')
            ->where(' StartTime < "' . date('Y-m-d H:i:s') . '"')
            // ->where(' Video = "[]" or Video = "" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'https://m.zhibo8.cc/json/video/zuqiu/' . $date . '.json';
        // vendor("phpQuery.phpQuery");
        if(!self::remote_file_exists($url)) {
            return false;
        }
        $json = @file_get_contents($url);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return false;
        }

        foreach ($arr['video_arr'] as $v) {  //  foreach loop 01  -- video_arr

            $Furl = 'https://m.zhibo8.cc' . $v['url'];
            if(!self::remote_file_exists($Furl)) {
                continue;
            }
            $html = @file_get_contents($Furl);
            $doc = phpQuery::newDocumentHTML($html, 'utf-8');
            phpQuery::selectDocument($doc);

            foreach (pq('div.signals a') as $dicBox) {  //  foreach loop 02  -- div.signals a

                $href = $dicBox->getAttribute('href');
                if ($href != 'http://m.zhibo8.cc/object/?from=mzhibo8cc' && // 流畅点播（直播吧客户端观看）
                    $href != 'http://m.zhibo8.cc/zuqiu/luxiang.htm'         // 如有加时赛请点此观看
                ) {
                    $tabBox = [];
                    $tabBox['webUrl'] = $href;
                    $tabBox['title'] = GarbledToUtf8($dicBox->textContent);
                    $tabBox['which'] = self::getFromWhichWeb($tabBox['title']);

                    // 是全场集锦的
                    if (
                        mb_strpos($tabBox['title'], "全场".$keyName1, 0, 'utf-8') !== false ||
                        mb_strpos($tabBox['title'], "原声".$keyName1, 0, 'utf-8') !== false ||
                        mb_strpos($tabBox['title'], "国语".$keyName1, 0, 'utf-8') !== false
                    ) {
                        foreach ($gameList as $gameone) {   // foreach loop 03  -- $gameList to $gameone
                            // 基于主队和客队匹配是否为这场比赛
                            if (
                                mb_strpos($tabBox['title'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                                mb_strpos($tabBox['title'], $gameone['BeTeamName'], 0, 'utf-8') !== false
                            ) {
                                $TagName1 = $tabBox['which'].$keyName1;
                                $flm = FootballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                                $oj = json_decode($flm['Video'], true);
                                if (!$oj) { // video字段为空
                                    $oj = [];
                                    $oj[] = ['source' => $TagName1, 'url' => $tabBox['webUrl']];
                                } else {    // video字段有数据
                                    $exists = false;
                                    foreach ($oj as $k1 => $o1) {
                                        if ($o1['source'] == $TagName1) {
                                            $exists = true;
                                            $oj[$k1]['url'] = $tabBox['webUrl'];
                                        } elseif ($o1['source'] == '') {
                                            unset($oj[$k1]);
                                        } elseif (mb_strpos($o1['source'],$keyName1,0,'utf-8') === false &&  // 集锦出来时把直播全部删掉
                                                  mb_strpos($o1['source'],$keyName2,0,'utf-8') === false )
                                        {
                                            // "腾讯直播" 保留并更名为 "腾讯体育集锦"
                                            if (mb_strpos($o1['source'],$keyName3,0,'utf-8') !== false ){
                                                $oj[$k1]['source'] = "腾讯体育集锦";
                                            }else {
                                                unset($oj[$k1]);
                                            }
                                        }
                                    }
                                    if (!$exists) { // 之前没有过这个标签才写进
                                        $oj[] = ['source' => $TagName1, 'url' => $tabBox['webUrl']];
                                    }
                                }

                                $flm['Video'] = (string)json_encode(array_values($oj));
                                $flm->save(); break 1;

                            }  // 包含TeamName和BeTeamName

                        }  // foreach loop 03  -- $gameList to $gameone

                    } // 全场集锦


                    // 是录像的
                    if (mb_strpos($tabBox['title'], $keyName2, 0, 'utf-8') !== false) {

                        foreach ($gameList as $gameone) {  // foreach loop 03  -- $gameList to $gameone
                            // 基于主队和客队匹配是否为这场比赛
                            if (
                                mb_strpos($tabBox['title'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                                mb_strpos($tabBox['title'], $gameone['BeTeamName'], 0, 'utf-8') !== false
                            ) {
                                $TagName2 = $tabBox['which'].$keyName2;
                                $flm = FootballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                                $oj = json_decode($flm['Video'], true);
                                if (!$oj) { // video字段为空
                                    $oj = [];
                                    $oj[] = ['source' => $TagName2, 'url' => $tabBox['webUrl']];
                                } else {    // video字段有数据
                                    $exists = false;
                                    foreach ($oj as $k1 => $o1) {
                                        if ($o1['source'] == $TagName2) {
                                            $exists = true;
                                            $oj[$k1]['url'] = $tabBox['webUrl'];
                                        } elseif ($o1['source'] == '') {
                                            unset($oj[$k1]);
                                        }
                                    }
                                    if (!$exists) { // 之前没有过这个标签才写进
                                        $oj[] = ['source' => $TagName2, 'url' => $tabBox['webUrl']];
                                    }
                                }

                                $flm['Video'] = (string)json_encode(array_values($oj));
                                $flm->save(); break 1;

                            } // 包含TeamName和BeTeamName

                        } // foreach loop 03  -- $gameList to $gameone

                    } // 录像

                } // 去掉客户端专用链接

            }  // foreach loop 02  -- div.signals a

        }  //  foreach loop 01  -- video_arr

    }  // function getFootballLeagueMatchs


    /**
     * @desc 抓取篮球比赛视频
     */
    public static function getBasketballMatchsVideo($date){
        $keyName1 = '集锦';      // 标签名1
        $keyName2 = '录像';      // 标签名2
        $keyName3 = '腾讯直播';  // 标签名3

        $gameList = BasketballLeagueMatchs::where(' LeagueID in (1,2,3) ')
            ->where(' StartTime > "' . date('Y-m-d 00:00:00') . '"')
            ->where(' StartTime < "' . date('Y-m-d H:i:s') . '"')
            // ->where(' Video = "[]" or Video = "" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'https://m.zhibo8.cc/json/video/nba/' . $date . '.json';
        // vendor("phpQuery.phpQuery");
        if(!self::remote_file_exists($url)) {
            return false;
        }
        $json = @file_get_contents($url);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return false;
        }

        foreach ($arr['video_arr'] as $v) {  //  foreach loop 01  -- video_arr

            $Furl = 'https://m.zhibo8.cc' . $v['url'];
            if(!self::remote_file_exists($Furl)) {
                continue;
            }
            $html = @file_get_contents($Furl);
            $doc = phpQuery::newDocumentHTML($html, 'utf-8');
            phpQuery::selectDocument($doc);

            foreach (pq('div.signals a') as $dicBox) {  //  foreach loop 02  -- div.signals a

                $href = $dicBox->getAttribute('href');
                if ($href != 'http://m.zhibo8.cc/object/?from=mzhibo8cc' && // 流畅点播（直播吧客户端观看）
                    $href != 'http://m.zhibo8.cc/nba/luxiang.htm'           // 如有加时赛请点此观看
                ) {
                    $tabBox = [];
                    $tabBox['webUrl'] = $href;
                    $tabBox['title'] = GarbledToUtf8($dicBox->textContent);
                    $tabBox['which'] = self::getFromWhichWeb($tabBox['title']);
                    // $tabBox['realUrl'] = $this->handleSource($tabBox['which'], $href);

                    // 是全场集锦的
                    if (
                        mb_strpos($tabBox['title'], "全场".$keyName1, 0, 'utf-8') !== false ||
                        mb_strpos($tabBox['title'], "原声".$keyName1, 0, 'utf-8') !== false ||
                        mb_strpos($tabBox['title'], "国语".$keyName1, 0, 'utf-8') !== false
                    ) {
                        foreach ($gameList as $gameone) {   // foreach loop 03  -- $gameList to $gameone
                            // 基于主队和客队匹配是否为这场比赛
                            if (
                                mb_strpos($tabBox['title'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                                mb_strpos($tabBox['title'], $gameone['BeTeamName'], 0, 'utf-8') !== false
                            ) {
                                $TagName1 = $tabBox['which'].$keyName1;
                                $flm = BasketballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                                $oj = json_decode($flm['Video'], true);
                                if (!$oj) { // video字段为空
                                    $oj = [];
                                    $oj[] = ['source' => $TagName1, 'url' => $tabBox['webUrl']];
                                } else {    // video字段有数据
                                    $exists = false;
                                    foreach ($oj as $k1 => $o1) {
                                        if ($o1['source'] == $TagName1) {
                                            $exists = true;
                                            $oj[$k1]['url'] = $tabBox['webUrl'];
                                        } elseif ($o1['source'] == '') {
                                            unset($oj[$k1]);
                                        } elseif (mb_strpos($o1['source'],$keyName1,0,'utf-8') === false &&  // 集锦出来时把直播全部删掉
                                                  mb_strpos($o1['source'],$keyName2,0,'utf-8') === false )
                                        {
                                            // "腾讯直播" 保留并更名为 "腾讯体育集锦"
                                            if (mb_strpos($o1['source'],$keyName3,0,'utf-8') !== false ){
                                                $oj[$k1]['source'] = "腾讯体育集锦";
                                            }else {
                                                unset($oj[$k1]);
                                            }
                                        }
                                    }
                                    if (!$exists) { // 之前没有过这个标签才写进
                                        $oj[] = ['source' => $TagName1, 'url' => $tabBox['webUrl']];
                                    }
                                }

                                $flm['Video'] = (string)json_encode(array_values($oj));
                                $flm->save(); break 1;

                            }  // 包含TeamName和BeTeamName

                        }  // foreach loop 03  -- $gameList to $gameone

                    } // 全场集锦


                    // 是录像的
                    if (mb_strpos($tabBox['title'], "录像", 0, 'utf-8') !== false) {

                        foreach ($gameList as $gameone) {  // foreach loop 03  -- $gameList to $gameone
                            // 基于主队和客队匹配是否为这场比赛
                            if (
                                mb_strpos($tabBox['title'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                                mb_strpos($tabBox['title'], $gameone['BeTeamName'], 0, 'utf-8') !== false
                            ) {
                                $TagName2 = $tabBox['which'].$keyName2;
                                $flm = BasketballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                                $oj = json_decode($flm['Video'], true);
                                if (!$oj) { // video字段为空
                                    $oj = [];
                                    $oj[] = ['source' => $TagName2, 'url' => $tabBox['webUrl']];

                                } else {    // video字段有数据
                                    $exists = false;
                                    foreach ($oj as $k1 => $o1) {
                                        if ($o1['source'] == $TagName2) {
                                            $exists = true;
                                            $oj[$k1]['url'] = $tabBox['webUrl'];
                                        } elseif ($o1['source'] == '') {
                                            unset($oj[$k1]);
                                        }
                                    }
                                    if (!$exists) { // 之前没有过这个标签才写进
                                        $oj[] = ['source' => $TagName2, 'url' => $tabBox['webUrl']];
                                    }
                                }

                                $flm['Video'] = (string)json_encode(array_values($oj));
                                $flm->save(); break 1;

                            } // 包含TeamName和BeTeamName

                        } // foreach loop 03  -- $gameList to $gameone

                    } // 录像

                } // 去掉客户端专用链接

            }  // foreach loop 02  -- div.signals a

        }  //  foreach loop 01  -- video_arr

    }  // function getBasketballMatchsVideo


    /**
     * 腾讯体育视频直播抓取
     * columnId
     * 足球
     * 8      = 英超 = 2
     * 21     = 意甲 = 5
     * 23     = 西甲 = 3
     * 208    = 中超 = 1
     * 605    = 亚冠 = 23
     * 5      = 欧冠 = 7
     * 6      = 欧联杯 = 15
     * 22     = 德甲 = 4
     * 24     = 法甲 = 6
     * 941    = 欧国联 = 97
     * 4      = 世界杯 = 49  out
     * 14     = 苏超    out
     * 129    = 俄超    out
     * 9      = 荷甲    out
     * 99     = 葡超    out
     * 115    = 土超    out
     * 214    = 澳超    out
     * 100351 = 足协杯  out
     */
    public static function getTencentFootballMatchsLive($date, $columnId){

        $keyName = '腾讯直播';  // 标签名
        if ($columnId == '8') {
            $LeagueID = 2;
        } elseif ($columnId == '21') {
            $LeagueID = 5;
        } elseif ($columnId == '23') {
            $LeagueID = 3;
        } elseif ($columnId == '208') {
            $LeagueID = 1;
        } elseif ($columnId == '605') {
            $LeagueID = 23;
        } elseif ($columnId == '5') {
            $LeagueID = 7;
        } elseif ($columnId == '6') {
            $LeagueID = 15;
        } elseif ($columnId == '22') {
            $LeagueID = 4;
        } elseif ($columnId == '24') {
            $LeagueID = 6;
        } elseif ($columnId == '4') {
            $LeagueID = 49;
        } elseif ($columnId == '941') {
            $LeagueID = 97;
        } else {
            die('联赛错误');
        }

        $gameList = FootballLeagueMatchs::where(' LeagueID = ' . $LeagueID)
            ->where(' StartTime > "' . $date . ' 00:00:00" ')
            ->where(' StartTime < "' . $date . ' 23:59:59" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'http://matchweb.sports.qq.com/matchUnion/list?startTime=' . $date . '&endTime=' . $date . '&columnId=' . $columnId . '&index=1';
        $json = @file_get_contents($url);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return false;
        }
        if (!isset($arr['data'][$date])) {
            return false;
        }

        // rightName = TeamName   =  主场
        // leftName  = BeTeamName =  客场
        foreach ($arr['data'][$date] as $matchone) {  //  foreach loop 01  -- 腾讯的每场信息

            foreach ($gameList as $gameone) {     //  foreach loop 02  -- $gameList to $gameone

                // 基于主队和客队匹配是否为这场比赛
                $isIn = false;
                if ((
                        mb_strpos($matchone['rightName'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                        mb_strpos($matchone['leftName'], $gameone['TeamName'], 0, 'utf-8') !== false
                    ) && $matchone['isPay'] == '0'  // 免费比赛
                ) {
                    $isIn = true;
                }

                // 基于主队和客队匹配是否为这场比赛
                if ($isIn) {
                    // 直播的网页链接
                    $LiveUrl = 'http://sports.qq.com/kbsweb/game.htm?mid=' . $matchone['mid'];
                    $flm = FootballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                    $oj = json_decode($flm['Video'], true);
                    if (!$oj) { // video字段为空
                        $oj = [];
                        $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                        $flm['Video'] = (string)json_encode($oj);
                        $flm->save();
                        break 1;

                    } else {    // video字段有数据
                        $isIn2 = true;
                        foreach($oj as $ojOne){
                            if($ojOne['source'] == $keyName){  // 已经有该标签
                                $isIn2 = false;
                            }
                        }

                        if ($isIn2) {  // 在没有该标签的情况下追加写入
                            $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                            $flm['Video'] = (string)json_encode($oj);
                            $flm->save();
                        }
                        break 1;
                    }


                }  // $isIn == true

            }  // foreach loop 02  -- $gameList to $gameone

        }  //  foreach loop 01  -- 腾讯的每场信息

    }  // function getTencentFootballMatchsLive



    /**
     * PPTV视频直播抓取
     */
    public static function getPPTVFootballMatchsLive($date, $index){

        $keyName = 'PPTV直播';  // 标签名
        $gameList = FootballLeagueMatchs::where(' StartTime > "' . $date . ' 00:00:00" ')
            ->where(' StartTime < "' . $date . ' 23:59:59" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'http://aplus.pptv.com/inapi/live/pg_sports?&cb=&start='.$index.'&end='.$index.'&plt=web';
        $json = @file_get_contents($url);
        $arr = json_decode($json, true);
        if (!is_array($arr)) { return false; }

        // homeTeamTitle   = TeamName   =  主场
        // guestTeamTitle  = BeTeamName =  客场
        foreach ($arr['data']['list'] as $eachDayList) {  //  foreach loop 01  -- PPTV的每天信息

            foreach ($eachDayList as $matchone) {         //  foreach loop 02  -- PPTV的每场比赛信息

                foreach ($gameList as $gameone) {         //  foreach loop 03  -- $gameList to $gameone
                    // 基于主队和客队匹配是否为这场比赛
                    $isIn = false;
                    if ((
                        mb_strpos($matchone['homeTeamTitle'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                        mb_strpos($matchone['guestTeamTitle'], $gameone['TeamName'], 0, 'utf-8') !== false)
                        && $matchone['vip_pay'] == 0 && $matchone['program_pay'] == 0 && $matchone['stream']['ispay'] == 0
                        // 免费比赛
                    ) {
                      $isIn = true;
                    }

                    // 基于主队和客队匹配是否为这场比赛
                    if ($isIn) {
                        // 直播的网页链接
                        $LiveUrl = $matchone['stream']['link'];
                        $flm = FootballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                        $oj = json_decode($flm['Video'], true);
                        if (!$oj) { // video字段为空
                            $oj = [];
                            $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                            $flm['Video'] = (string)json_encode($oj);
                            $flm->save();
                            break 1;

                        } else {    // video字段有数据
                            $isIn2 = true;
                            foreach($oj as $ojOne){
                                if($ojOne['source'] == $keyName){  // 已经有该标签
                                    $isIn2 = false;
                                }
                            }

                            if ($isIn2) {  // 在没有该标签的情况下追加写入
                                $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                                $flm['Video'] = (string)json_encode($oj);
                                $flm->save();
                            }
                            break 1;
                        }

                    }  // $isIn == true

                }  // foreach loop 03  -- $gameList to $gameone

            }  //  foreach loop 02  -- PPTV的每场比赛信息

        }  //  foreach loop 01  -- PPTV的每天信息

    }  // function getPPTVFootballMatchsLive



    /**
     * columnId
     * 篮球
     * 100000 = NBA = 1
     * 100008 = CBA = 2
     */
    public static function getTencentBasketballMatchsLive($date, $columnId)
    {
        if ($columnId == '100000') {
            $LeagueID = 1;
        } elseif ($columnId == '100008') {
            $LeagueID = 2;
        } else {
            die('联赛错误');
        }

        $gameList = BasketballLeagueMatchs::where(' LeagueID = ' . $LeagueID)
            ->where(' StartTime > "' . $date . ' 00:00:00" ')
            ->where(' StartTime < "' . $date . ' 23:59:59" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'http://matchweb.sports.qq.com/matchUnion/list?startTime=' . $date . '&endTime=' . $date . '&columnId=' . $columnId . '&index=1';
        $json = @file_get_contents($url);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return false;
        }
        if (!isset($arr['data'][$date])) {
            return false;
        }

        // rightName = TeamName   =  主场
        // leftName  = BeTeamName =  客场
        foreach ($arr['data'][$date] as $matchone) {  //  foreach loop 01  -- 腾讯的每场信息
            foreach ($gameList as $gameone) {     //  foreach loop 02  -- $gameList to $gameone

                // 基于主队和客队匹配是否为这场比赛
                $isIn = false;
                if (  // nba
                    (mb_strpos($matchone['rightName'], $gameone['TeamName'], 0, 'utf-8') !== false ||
                        mb_strpos($matchone['leftName'], $gameone['BeTeamName'], 0, 'utf-8') !== false)
                    && $columnId == '100000'
                    && $matchone['isPay'] == '0'  // 免费比赛
                ) {
                    $isIn = true;
                }

                if (  // cba 不知名原因cba的字段跟nba的主客是相反的
                    (mb_substr($matchone['rightName'], 0, 2, 'utf-8') == mb_substr($gameone['BeTeamName'], 0, 2, 'utf-8') ||
                        mb_substr($matchone['leftName'], 0, 2, 'utf-8') == mb_substr($gameone['TeamName'], 0, 2, 'utf-8'))
                    && $columnId == '100008'
                    && $matchone['isPay'] == '0'  // 免费比赛
                ) {
                    $isIn = true;
                }
                // 基于主队和客队匹配是否为这场比赛
                if ($isIn) {
                    $flm = BasketballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                    $oj = json_decode($flm['Video'], true);
                    if (!$oj) { // video字段为空
                        $oj = [];
                        $oj[] = ['source' => '腾讯直播', 'url' => 'http://sports.qq.com/kbsweb/game.htm?mid=' . $matchone['mid']];
                        $flm['Video'] = (string)json_encode($oj);
                        $flm->save();
                        break 1;

                    } else {    // video字段有数据
                        // 肯定是同等的直播链接 忽略
                        break 1;
                    }

                }  // $isIn == true

            }  // foreach loop 02  -- $gameList to $gameone

        }  //  foreach loop 01  -- 腾讯的每场信息

    }  // function getTencentBasketballMatchsLive



    /**
     * CCTV视频篮球直播抓取
     * 暂时只能抓取NBA的
     */
    public static function getCCTVBasketballMatchsLive($date){
        $keyName1 = 'CCTV直播';
        $LiveUrl1 = 'http://tv.cctv.com/live/cctv5/';  // 永远固定直播链接
        $keyName2 = 'CCTV文字';

        $gameList = BasketballLeagueMatchs::where(' LeagueID = 1 ')
            ->where(' StartTime > "' . $date . ' 00:00:00" ')
            ->where(' StartTime < "' . $date . ' 23:59:59" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'http://api.cntv.cn/epg/epginfo?serviceId=tvcctv&c=cctv5&d='.str_replace("-","",$date).'&cb=jQuery&t=jsonp&_=';
        $json = @substr(file_get_contents($url),7,-2);
        $arr = json_decode($json, true);
        if (!is_array($arr)) { return false; }

        // TeamName   =  主场
        // BeTeamName =  客场
        foreach ($arr['cctv5']['program'] as $matchone) {  //  foreach loop 01  -- CCTV的每场比赛信息

            foreach ($gameList as $gameone) {         //  foreach loop 02 -- $gameList to $gameone

                // 基于主队和客队匹配是否为这场比赛
                $isIn = false;
                if (   (mb_strpos($matchone['t'], $gameone['TeamName'],   0, 'utf-8') !== false ||
                        mb_strpos($matchone['t'], $gameone['BeTeamName'], 0, 'utf-8') !== false)
                    && $matchone['eventType'] == 'NBA'  // 是NBA比赛
                ) {
                  $isIn = true;
                }

                // 基于主队和客队匹配是否为这场比赛
                if ($isIn) {
                    $flm = BasketballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                    $oj = json_decode($flm['Video'], true);
                    if (!$oj) { // video字段为空
                        $oj = [];
                        $oj[] = ['source' => $keyName1, 'url' => $LiveUrl1];
                        $oj[] = ['source' => $keyName2, 'url' => $matchone['eventId']];

                    } else {    // video字段有数据
                        $isIn2 = true;
                        foreach($oj as $ojOne){
                            if($ojOne['source'] == $keyName1 || $ojOne['source'] == $keyName2){  // 已经有该标签
                                $isIn2 = false;
                            }
                        }
                        if ($isIn2) {  // 在没有该标签的情况下追加写入
                            $oj[] = ['source' => $keyName1, 'url' => $LiveUrl1];
                            $oj[] = ['source' => $keyName2, 'url' => $matchone['eventId']];
                        }
                    }

                    $flm['Video'] = (string)json_encode($oj);
                    $flm->save(); break 1;
                }  // $isIn == true

            }  // foreach loop 02  -- $gameList to $gameone

        }  //  foreach loop 01  -- CCTV的每场比赛信息

    }  // function getCCTVBasketballMatchsLive


    /**
     * CCTV视频足球直播抓取
     */
    public static function getCCTVFootballMatchsLive($date){
        $keyName1 = 'CCTV直播';
        $LiveUrl1 = 'http://tv.cctv.com/live/cctv5/';  // 永远固定直播链接

        $gameList = FootballLeagueMatchs::where(' LeagueID in (2,3,5,4,6,7,1,23,15,49,97) ')
            ->where(' StartTime > "' . $date . ' 00:00:00" ')
            ->where(' StartTime < "' . $date . ' 23:59:59" ')
            ->field('ID,TeamName,BeTeamName,StartTime,Video')
            ->select();

        $url = 'http://api.cntv.cn/epg/epginfo?serviceId=tvcctv&c=cctv5&d='.str_replace("-","",$date).'&cb=jQuery&t=jsonp&_=';
        $json = @substr(file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>30]])),7,-2);
        
        $arr = json_decode($json, true);
        if (!is_array($arr)) { return false; }

        // TeamName   =  主场
        // BeTeamName =  客场
        foreach ($arr['cctv5']['program'] as $matchone) {  //  foreach loop 01  -- CCTV的每场比赛信息

            foreach ($gameList as $gameone) {         //  foreach loop 02 -- $gameList to $gameone

                // 基于主队和客队匹配是否为这场比赛
                $isIn = false;
                if (   (mb_strpos($matchone['t'], $gameone['TeamName'],   0, 'utf-8') !== false ||
                        mb_strpos($matchone['t'], $gameone['BeTeamName'], 0, 'utf-8') !== false)
                ) {
                  $isIn = true;
                }

                // 基于主队和客队匹配是否为这场比赛
                if ($isIn) {
                    $flm = FootballLeagueMatchs::where(['ID' => $gameone['ID']])->find();
                    $oj = json_decode($flm['Video'], true);
                    if (!$oj) { // video字段为空
                        $oj = [];
                        $oj[] = ['source' => $keyName1, 'url' => $LiveUrl1];
                    } else {    // video字段有数据
                        $isIn2 = true;
                        foreach($oj as $ojOne){
                            if($ojOne['source'] == $keyName1){  // 已经有该标签
                                $isIn2 = false;
                            }
                        }
                        if ($isIn2) {  // 在没有该标签的情况下追加写入
                            $oj[] = ['source' => $keyName1, 'url' => $LiveUrl1];
                        }
                    }

                    $flm['Video'] = (string)json_encode($oj);
                    $flm->save();
                    break 1;
                }  // $isIn == true

            }  // foreach loop 02  -- $gameList to $gameone

        }  //  foreach loop 01  -- CCTV的每场比赛信息

    }  // function getCCTVFootballMatchsLive



    /**
     * @desc 根据tabBox标题判断原视频来源网站
     */
    private static function getFromWhichWeb($title){
        $title = str_replace("全场","",$title);
        $title = str_replace("原声","",$title);
        $title = str_replace("国语","",$title);
        $title = str_replace("集锦","",$title);
        $title = str_replace("录像","",$title);
        $s = mb_strpos($title, "[", 0, 'utf-8');
        $e = mb_strpos($title, "]", 0, 'utf-8');
        return mb_substr($title, $s + 1, ($e - $s - 1), 'utf-8');
    }


    /**
     * @desc 抓取无插件网站直播链接
     */
    public static function getWuChaJianLive(){

        $wuchajianList = self::getWuChaJianItems();

        foreach ($wuchajianList as $wuchajianOne) {

            if($wuchajianOne['cagetory'] == "足球") {

                $game = FootballLeagueMatchs::where(' LeagueID in (2,3,5,4,6,7,1,23,15,49,97) ')
                    ->where(' TeamName   like "%' . $wuchajianOne['home'] . '%" ')
                    ->where(' BeTeamName like "%' . $wuchajianOne['away'] . '%" ')
                    ->where(' StartTime > "' . substr($wuchajianOne['time'], 0, 10) . ' 00:00:00" ')
                    ->where(' StartTime < "' . substr($wuchajianOne['time'], 0, 10) . ' 23:59:59" ')
                    ->field('ID,TeamName,BeTeamName,StartTime,Video')
                    ->find();

                if (!empty($game)) {
                    foreach ($wuchajianOne['links'] as $keys => $LiveUrl) {
                        // 直播的网页链接
                        $keyName = '无插件网' . ($keys + 1);
                        $oj = json_decode($game['Video'], true);
                        if (!$oj) { // video字段为空
                            $oj = [];
                            $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                            $game['Video'] = (string)json_encode($oj);
                            $game->save();
                        } else {    // video字段有数据
                            $isIn2 = true;
                            foreach ($oj as $ojOne) {
                                if ($ojOne['source'] == $keyName) {  // 已经有该标签
                                    $isIn2 = false;
                                }
                            }

                            if ($isIn2) {  // 在没有该标签的情况下追加写入
                                $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                                $game['Video'] = (string)json_encode($oj);
                                $game->save();
                            }
                        }
                    }
                }
            }

            if($wuchajianOne['cagetory'] == "篮球") {

                $game = BasketballLeagueMatchs::where(' LeagueID in (1,2,3) ')
                    ->where(' TeamName   like "%' . $wuchajianOne['home'] . '%" ')
                    ->where(' BeTeamName like "%' . $wuchajianOne['away'] . '%" ')
                    ->where(' StartTime > "' . substr($wuchajianOne['time'], 0, 10) . ' 00:00:00" ')
                    ->where(' StartTime < "' . substr($wuchajianOne['time'], 0, 10) . ' 23:59:59" ')
                    ->field('ID,TeamName,BeTeamName,StartTime,Video')
                    ->find();

                if (!empty($game)) {
                    foreach ($wuchajianOne['links'] as $keys => $LiveUrl) {
                        // 直播的网页链接
                        $keyName = '无插件网' . ($keys + 1);
                        $oj = json_decode($game['Video'], true);
                        if (!$oj) { // video字段为空
                            $oj = [];
                            $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                            $game['Video'] = (string)json_encode($oj);
                            $game->save();
                        } else {    // video字段有数据
                            $isIn2 = true;
                            foreach ($oj as $ojOne) {
                                if ($ojOne['source'] == $keyName) {  // 已经有该标签
                                    $isIn2 = false;
                                }
                            }

                            if ($isIn2) {  // 在没有该标签的情况下追加写入
                                $oj[] = ['source' => $keyName, 'url' => $LiveUrl];
                                $game['Video'] = (string)json_encode($oj);
                                $game->save();
                            }
                        }
                    }
                }
            }

        }

    }  // function getWuChaJianLive


    /**
     * @desc 抓取有效列表
     */
    public static function getWuChaJianItems(){

        $wuchajianList  = QueryList::get('http://www.wuchajian.net')->rules([
            'matcha' => ['td.matcha','title'],
            'home'  => ['td.teama[id*="team_"] > a > strong','text'],
            'away'  => ['td.teama[d*="team_"] > a > strong','text'],
            'cagetory'  => ['td:eq(0)','title'],
            'time'  => ['td.tixing','t'],
            'link1'  => ['td.live_link a:eq(0)','href'],
            'link2'  => ['td.live_link a:eq(1)','href'],
            'link3'  => ['td.live_link a:eq(2)','href'],
            'link4'  => ['td.live_link a:eq(3)','href'],
        ])->range('tr.against')
          ->query()
          ->getData()
          ->all();

        foreach ($wuchajianList as $k1 => $wuchajianOne){
            $wuchajianList[$k1]['matcha'] = str_replace("直播","",$wuchajianList[$k1]['matcha']);
            $wuchajianList[$k1]['matcha'] = str_replace(" ","",$wuchajianList[$k1]['matcha']);

            if($wuchajianOne['away'] == "" || $wuchajianOne['home'] == ""){
                unset($wuchajianList[$k1]);
            }
            if($wuchajianOne['cagetory'] != "篮球" && $wuchajianOne['cagetory'] != "足球"){
                unset($wuchajianList[$k1]);
            }
            if(
                mb_strpos($wuchajianOne['matcha'], "英超", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "德甲", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "西甲", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "意甲", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "法甲", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "中超", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "亚冠", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "欧冠", 0, 'utf-8') === false &&
                mb_strpos($wuchajianOne['matcha'], "欧联", 0, 'utf-8') === false &&
//                mb_strpos($wuchajianOne['matcha'], "世界杯", 0, 'utf-8') === false &&
                stripos($wuchajianOne['matcha'], "NBA") === false &&
                stripos($wuchajianOne['matcha'], "CBA") === false
            ){
                unset($wuchajianList[$k1]);
            }


            if(isset($wuchajianList[$k1])){
                $wuchajianList[$k1]['links'] = [];
                $link1 = self::WuChaJianFilterLink($wuchajianOne['link1']);
                if($link1 !== false){
                    $wuchajianList[$k1]['links'][] = $link1;
                }

                $link2 = self::WuChaJianFilterLink($wuchajianOne['link2']);
                if($link2 !== false){
                    $wuchajianList[$k1]['links'][] = $link2;
                }

                $link3 = self::WuChaJianFilterLink($wuchajianOne['link3']);
                if($link3 !== false){
                    $wuchajianList[$k1]['links'][] = $link3;
                }

                $link4 = self::WuChaJianFilterLink($wuchajianOne['link4']);
                if($link4 !== false){
                    $wuchajianList[$k1]['links'][] = $link4;
                }
                unset($wuchajianList[$k1]['link1']);
                unset($wuchajianList[$k1]['link2']);
                unset($wuchajianList[$k1]['link3']);
                unset($wuchajianList[$k1]['link4']);

                if(count($wuchajianList[$k1]['links']) == 0){
                    unset($wuchajianList[$k1]);
                }

            }

        } // foreach

        $wuchajianList = array_values($wuchajianList);
        return $wuchajianList;
    }

    /**
     * @desc 过滤直播链接
     */
    private static function WuChaJianFilterLink($link){
        if($link == ""){
            return false;
        }

        if($link == "../score/?link"){
           return false;
        }

        if($link == "/basket/"){
           return false;
        }

        if(substr($link,0,12) == "/zhibo/live-"){
           return false;
        }

        if(substr($link,0,20) == "http://sports.qq.com"){
           return false;
        }

        if(substr($link,0,6) == "../tv/"){
           return "http://www.wuchajian.net/".substr($link,3);
        }

        return $link;
    }


    /**
     * 检查远程文件是否存在
     */
    public static function remote_file_exists($url) {
        $curl = curl_init($url);
        // 不取回数据
        curl_setopt($curl, CURLOPT_NOBODY, true);
        // 发送请求
        $result = curl_exec($curl);
        $found = false;
        // 如果请求没有发送失败
        if ($result !== false) {
            // 再检查http响应码是否为200
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($statusCode == 200) {
                $found = true;
            }
        }
        curl_close($curl);
        return $found;
    }


    // (废弃) - 获取真视频源
    /**
      *
    private function handleSource($which, $webUrl){

        if (
            mb_strpos($which, "秒拍", 0, 'utf-8') !== false ||
            mb_strpos($which, "微博", 0, 'utf-8') !== false ||
            strpos($webUrl, "weibo.com") !== false
        ) {

            $newWebUrl = 'https://m.weibo.cn/status/' . @end(explode("/", $webUrl));
            $html = $this->toMobileWeiBoVideo($newWebUrl);
            $jsScript = getScriptTag($html);
            if($jsScript){
                foreach ($jsScript[1] as $v1){
                    // 可以考虑 V8js（在 PHP 内部执行 JavaScript 的技术）
                    // @see https://www.zhihu.com/question/20094371

                    if(strpos($v1,"stream_url_hd") !== false ){  // 优先匹配高清
                        $s = strpos($v1,'"stream_url_hd": "',0);
                        $e = strpos($v1,'unistore,video"',($s+10));
                        return substr($v1, $s + 18, ($e-$s-4));
                    }elseif(strpos($v1,"stream_url") !== false ){  // 没高清寻找标清
                        $s = strpos($v1,'"stream_url": "',0);
                        $e = strpos($v1,'unistore,video"',0);
                        return substr($v1, $s+15, ($e-$s-1));
                    }

                }
            }
        }
    }
    */

     // (废弃) - 抓取weibo.com/tv/v/信息标签
     /**
      *
     private function toMobileWeiBoVideo($url){

        // 伪装 mobile useragent
        $UA = "Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1";

        // 伪装请求头
        $header = ["Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8", "Accept-Encoding" => "gzip, deflate, br", "Accept-Language" => "zh-CN,zh;q=0.9", "Cache-Control" => "max-age=0", "Connection" => "keep-alive", "Host" => "m.weibo.cn", "Upgrade-Insecure-Requests" => 1, "User-Agent" => $UA];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_HEADER, 0);            // 获取头部信息 debug
        curl_setopt($ch, CURLOPT_USERAGENT, $UA);

        // 设置伪装cookie 以后设置为读缓存
        curl_setopt($ch, CURLOPT_COOKIE, "_T_WM=5c091d17096da39e43d79d1c5fafe156; WEIBOCN_FROM=1110006030; M_WEIBOCN_PARAMS=oid%3D4203155728733160%26luicode%3D20000061%26lfid%3D4203155728733160%26uicode%3D20000061%26fid%3D4203155728733160");
        curl_setopt($ch, CURLOPT_URL, $url);

        $content = curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        //返回结果
        if ($content && $httpcode == 200) {
            return $content;
        } else {
            return false;
        }
     }
     */


}



