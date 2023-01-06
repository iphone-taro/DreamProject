<?php declare(strict_types=1);

namespace App\Http\Controllers;
use Imagick;
use ImagickDraw;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Temp;
use App\Models\Bookmark;
use App\Models\Follow;
use App\Models\Mute;
use App\Models\Post;
use App\Models\Stamp;
use App\Consts\Consts;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailMgr;

use Abraham\TwitterOAuth\TwitterOAuth;

class DreamController extends Controller
{   
    //
    //広告情報取得
    //
    public function getAds(): JsonResponse {
        $ads = file('../resources/ads.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $retArray = array();
        for ($i=0; $i < count($ads); $i++) { 
            $adInfo = explode(',',  $ads[$i]);
            array_push($retArray, ['file' => $adInfo[0], 'url' => $adInfo[1]]);
        }
        return response()->json(['ads' => $retArray]);
    }

    //
    // SNS用設定
    //
    public function snsAction($id) {
        $title = "yumedrop 作品ページ";
        $description = "yumedropに投稿された作品のページです";
        // $card = "https://iphone-taro.sakura.ne.jp/yumedrop/storage/card/";
        $card = "card_base.jpg";

        //パラメータあり
        $postId = $id;

        //照合
        if (mb_strlen($postId) == 20) {
            //長さOK
            //DB照合
            $postData = DB::table('posts as post')->where('publishing', '<>', 99)->where('post.post_id', $postId)->first();
            
            if ($postData != null) {
                //データあり
                $title = $postData->title; 
                $description = $postData->outline;

                // //カード画像データがあるか
                // if (file_exists('../storage/app/public/card/card_' . $postId . '.jpg')) { 
                //     //結果画像ある
                //     $card = 'card_' . $postId . '.jpg'; 
                // }
            }
        }
        $header = ['card' => $card, 'title' => $title, 'description' => $description];
        return view('spa.app')->with(['card' => $card, 'title' => $title, 'description' => $description]);
    }

    //
    //表現規制チェック
    //
    public function checkRegulation($str) {
        $regulationArray = array("穢多","キ印","黒んぼ","新平民","鮮人","育ちより氏","チャンコロ","チョン","土人","南鮮","半島人","非人");

        for ($i=0; $i < count($regulationArray); $i++) { 
            if (strpos($str, $regulationArray[$i]) == true) {
                return false;
            }
        }
        return true;
    }

    //
    //基本情報取得（認証確認含め）
    //
    public function getBaseInfo(): JsonResponse {
        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]); 
    }

    //
    //基本情報取得（認証確認含め）
    //
    public function retUserInfo() {
        $userData = Auth::User();

        $retData = array(
            'user_id' => $userData['user_id'],
            'user_name' => $userData['user_name'],
            'profile' => $userData['profile'],
            'twitter_code' => $userData['twitter_code'],
            'favorite_tag' => $userData['favorite_tag'],
            'mute_tag' => $userData['mute_tag'],
            'mail_address' => $userData['mail_address'],
            'show_twitter' => $userData['show_twitter'],
            'disp_r18' => $userData['disp_r18'],
            'disp_r18g' => $userData['disp_r18g'],
        );

        if ($userData['mail_address'] == '') {
            $retData = $retData + array('auth_mail' => '0');
        } else {
            $retData = $retData + array('auth_mail' => '1');
        }

        if ($userData['twitter_id'] == '') {
            $retData = $retData + array('auth_twitter' => '0');
        } else {
            $retData = $retData + array('auth_twitter' => '1');
        }

        return $retData;
    }
    

    //
    //シリーズリスト取得
    //
    public function getMySeriesList(Request $request): JsonResponse {
        // $retList = \DB::select('
        //     SELECT
		// 		posts.post_id,
		// 		posts.title,
		// 		posts.outline,
		// 		char_length(posts.body) AS len,
		// 		posts.series,
		// 		posts.rating,
		// 		posts.creation,
		// 		posts.tags,
		// 		posts.filter,
		// 		posts.publishing,
		// 		posts.view_count,
		// 		posts.updated_at,
		// 		IFNULL(bookmarks.bookmark_count, 0) AS bookmark,
		// 		IFNULL(stamps.stamp_count, 0) AS stamp 
		// 		FROM
		// 		posts
		// 		LEFT JOIN (SELECT post_id, count(*) AS bookmark_count FROM bookmarks GROUP BY post_id) AS bookmarks
		// 		 ON posts.post_id = bookmarks.post_id
		// 		LEFT JOIN (SELECT post_id, count(*) AS stamp_count  FROM stamps GROUP BY post_id) AS stamps
		// 		 ON posts.post_id = stamps.post_id
		// 		where posts.user_id = "' . $Auth::User()->user_id . '}"
		// 		ORDER BY posts.updated_at DESC
        // ');

        $seriesList = array("指定なし");
        $sList = post::where('user_id', auth::User()->user_id)->orderby('created_at', 'desc')->get(['series']);
        foreach ($sList as $data) {
            $flg = false;
            $val = $data["series"];
            for ($i=0; $i < count($seriesList); $i++) { 
                if ($val == $seriesList[$i]) {
                    $flg = true;
                }
            }
            if (!$flg) {
                array_push($seriesList, $val);
            }
        }
        
        return response()->json(['status' => Consts::API_SUCCESS, 'seriesList' => $seriesList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //新着一覧取得
    //
    public function getLatestPostList(Request $request): JsonResponse {
        $userData = Auth::User();

        $dispR18 = "0";
        $dispR18g = "0";
        if ($userData->disp_r18 == 1) {
            $dispR18 = "1";
        }
        if ($userData->disp_r18g == 1) {
            $dispR18g = "1";
        }
        $filterStr = "04411" . $dispR18 . $dispR18g . "1111111";
        $wordStr = "";

        $sql = $this->makePostListSql($filterStr, $wordStr, false);
        //件数
        $sql = $sql . "LIMIT 50";

        //対象データ取得
        $postList = \DB::select($sql);

        $retArray = array();
        $retArray = $retArray + array('sql' => $sql);
        $retArray = $retArray + array('postList' => $postList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());
        
        return response()->json($retArray);
    }

    //
    //検索一覧取得
    //
    public function getPostList(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'filter' => 'required|string|size:14',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $filterStr = $request->filter;
        $wordStr = "";
        if ($request->keyword != null) {
            $wordStr = trim(str_replace("　", " ", $request->keyword));
        }

        //フィルター分割
		$fOrder = substr($filterStr, 0, 1);
		$fDuration = substr($filterStr, 1, 1);
		$fLen = substr($filterStr, 2, 1);
		$fRating0 = substr($filterStr, 3, 1);
		$fRating1 = substr($filterStr, 4, 1);
		$fRating2 = substr($filterStr, 5, 1);
		$fRating3 = substr($filterStr, 6, 1);
		$fChara0 = substr($filterStr, 7, 1);
		$fChara1 = substr($filterStr, 8, 1);
		$fChara2 = substr($filterStr, 9, 1);
		$fCreation0 = substr($filterStr, 10, 1);
		$fCreation1 = substr($filterStr, 11, 1);
		$fConversion0 = substr($filterStr, 12, 1);
		$fConversion1 = substr($filterStr, 13, 1);

        //入力チェック
        if (($fOrder != 0 && $fOrder != 1 && $fOrder != 2) ||
		($fDuration != 0 && $fDuration != 1 && $fDuration != 2 && $fDuration != 3) ||
		($fLen != 0 && $fLen != 1 && $fLen != 2 && $fLen != 3) ||
		($fRating0 != 0 && $fRating0 != 1) ||
		($fRating1 != 0 && $fRating1 != 1) ||
		($fRating2 != 0 && $fRating2 != 1) ||
		($fRating3 != 0 && $fRating3 != 1) ||
		($fChara0 != 0 && $fChara0 != 1) ||
		($fChara1 != 0 && $fChara1 != 1) ||
		($fChara2 != 0 && $fChara2 != 1) ||
		($fCreation0 != 0 && $fCreation0 != 1) ||
		($fCreation1 != 0 && $fCreation1 != 1) ||
		($fConversion0 != 0 && $fConversion0 != 1) ||
		($fConversion1 != 0 && $fConversion1 != 1)) {
            //フィルターチェック
			return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
		}
        
        //対象ページ
        if ($request->page != null) {
            $page = $request->page;
        } else {
            $page = 1;
        }
        $start = ($page - 1) * 10;
        
        //件数取得用SQL
        $countSql = $this->makePostListSql($filterStr, $wordStr, true);
        $postCount = \DB::select($countSql);

        $sql = $this->makePostListSql($filterStr, $wordStr, false);
        //件数
        $sql = $sql . "LIMIT " . $start . ", 10";

        //対象データ取得
        $postList = \DB::select($sql);
        $retArray = array();
        $retArray = $retArray + array('sql' => $sql);
        $retArray = $retArray + array('count' => $postCount[0]->abc);
        $retArray = $retArray + array('postList' => $postList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());
        $retArray = $retArray + array('test' => $request->test);
        
        return response()->json($retArray);
    }

    //
    //投稿一覧取得SQL生成
    //
    public function makePostListSql($filterStr, $wordStr,  $isCount) {
        //ユーザーIDを取得
		$userId = Auth::User()->user_id;
		$muteStr = Auth::User()->mute_tag;

        $fOrder = substr($filterStr, 0, 1);
		$fDuration = substr($filterStr, 1, 1);
		$fLen = substr($filterStr, 2, 1);
		$fRating0 = substr($filterStr, 3, 1);
		$fRating1 = substr($filterStr, 4, 1);
		$fRating2 = substr($filterStr, 5, 1);
		$fRating3 = substr($filterStr, 6, 1);
		$fChara0 = substr($filterStr, 7, 1);
		$fChara1 = substr($filterStr, 8, 1);
		$fChara2 = substr($filterStr, 9, 1);
		$fCreation0 = substr($filterStr, 10, 1);
		$fCreation1 = substr($filterStr, 11, 1);
		$fConversion0 = substr($filterStr, 12, 1);
		$fConversion1 = substr($filterStr, 13, 1);
        
        $retArray = array();

        $sqlInit = "SELECT
            `posts`.`post_id`,
            `posts`.`user_id`,
            `users`.`user_name`,
            `posts`.`title`,
            `posts`.`outline`,
            char_length(`posts`.`body`) AS length,
            `posts`.`series`,
            `posts`.`rating`,
            `posts`.`chara`,
            `posts`.`creation`,
            `posts`.`tags`,
            `posts`.`created_at`,
            `mutes`.`mute_id`,
            CASE WHEN `bookmarks`.`user_id` IS NULL THEN 0 ELSE 1 END AS book";

        //SQLの生成
        $sql = "
            from
            `posts`
            LEFT JOIN `users`
            ON `posts`.`user_id` = `users`.`user_id`
            LEFT JOIN (select `post_id`, `user_id` FROM `bookmarks` WHERE `user_id` = '{$userId}') AS `bookmarks`
            ON `posts`.`post_id` = `bookmarks`.`post_id`
            LEFT JOIN (select * FROM mutes WHERE user_id = '" . $userId . "') AS mutes 
            ON `posts`.`user_id` = `mutes`.`mute_id` 
            WHERE
            `posts`.`searchable` = 1 AND
            `mutes`.`mute_id` is null AND 
            `posts`.`publishing` = 0 AND ";
        
        //フィルター条件追加
        //期間
        if ($fDuration == 1) {
            $sql = $sql . "`posts`.`created_at` > current_date AND ";
        } else if ($fDuration == 2) {
            $sql = $sql . "`posts`.`created_at` > DATE_SUB( NOW(),INTERVAL 7 DAY ) AND ";
        } else if ($fDuration == 3) {
            $sql = $sql . "`posts`.`created_at` > DATE_SUB( NOW(),INTERVAL 1 MONTH ) AND ";
        }
        
        //文字数
        if ($fLen == 1) {
            $sql = $sql . "char_length(`posts`.`body`) > 2000 AND ";
        } else if ($fLen == 2) {
            $sql = $sql . "char_length(`posts`.`body`) > 5000 AND ";
        } else if ($fLen == 3) {
            $sql = $sql . "char_length(`posts`.`body`) > 10000 AND ";
        }

        //レーティング
        if ($fRating0 == 1 || $fRating1 == 1 || $fRating2 == 1) {
            $fRatingStr = "(";
            if ($fRating0 == 1) {
                $fRatingStr = $fRatingStr . "`posts`.`rating` = 0 ";
            }
            if ($fRating1 == 1) {
                if ($fRatingStr == "(") {
                    $fRatingStr = $fRatingStr . "`posts`.`rating` = 1 ";
                } else {
                    $fRatingStr = $fRatingStr . "OR `posts`.`rating` = 1 ";
                }
            }
            if ($fRating2 == 1) {
                if ($fRatingStr == "(") {
                    $fRatingStr = $fRatingStr . "`posts`.`rating` = 2 ";
                } else {
                    $fRatingStr = $fRatingStr . "OR `posts`.`rating` = 2 ";
                }
            }
            if ($fRating3 == 1) {
                if ($fRatingStr == "(") {
                    $fRatingStr = $fRatingStr . "`posts`.`rating` = 3 ";
                } else {
                    $fRatingStr = $fRatingStr . "OR `posts`.`rating` = 3 ";
                }
            }
            $fRatingStr = $fRatingStr . ") AND ";
            $sql = $sql . $fRatingStr;
        } else {
            $sql = $sql . "(`posts`.`rating` = 4) AND ";
        }

        //主人公
        if ($fChara0 == 1 || $fChara1 == 1 || $fChara2 == 1) {
            $fCharaStr = "(";
            if ($fChara0 == 1) {
                $fCharaStr = $fCharaStr . "`posts`.`chara` = 0 ";
            }
            if ($fChara1 == 1) {
                if ($fCharaStr == "(") {
                    $fCharaStr = $fCharaStr . "`posts`.`chara` = 1 ";
                } else {
                    $fCharaStr = $fCharaStr . "OR `posts`.`chara` = 1 ";
                }
            }
            if ($fChara2 == 1) {
                if ($fCharaStr == "(") {
                    $fCharaStr = $fCharaStr . "`posts`.`chara` = 2 ";
                } else {
                    $fCharaStr = $fCharaStr . "OR `posts`.`chara` = 2 ";
                }
            }
            $fCharaStr = $fCharaStr . ") AND ";
            $sql = $sql . $fCharaStr;
        } else {
            $sql = $sql . "(`posts`.`chara` = 3) AND ";
        }

        //創作
        if ($fCreation0 == 1 || $fCreation1 == 1) {
            $fCreationStr = "(";
            if ($fCreation0 == 1) {
                $fCreationStr = $fCreationStr . "`posts`.`creation` = 0 ";
            }
            if ($fCreation1 == 1) {
                if ($fCreationStr == "(") {
                    $fCreationStr = $fCreationStr . "`posts`.`creation` = 1 ";
                } else {
                    $fCreationStr = $fCreationStr . "OR `posts`.`creation` = 1 ";
                }
            }
            $fCreationStr = $fCreationStr . ") AND ";
            $sql = $sql . $fCreationStr;
        } else {
            $sql = $sql . "(`posts`.`creation` = 2) AND ";
        }

        //名前変換
        if ($fConversion0 == 1 || $fConversion1 == 1) {
            $fConversionStr = "(";
            if ($fConversion0 == 1) {
                $fConversionStr = $fConversionStr . "`posts`.`conversion` != '' ";
            }
            if ($fConversion1 == 1) {
                if ($fConversionStr == "(") {
                    $fConversionStr = $fConversionStr . "`posts`.`conversion` = '' ";
                } else {
                    $fConversionStr = $fConversionStr . "OR `posts`.`conversion` = '' ";
                }
            }
            $fConversionStr = $fConversionStr . ") AND ";
            $sql = $sql . $fConversionStr;
        } else {
            $sql = $sql . "(`posts`.`conversion` = null) AND ";
        }

        //検索ワード設定
		$wordSql = "";
		if ($wordStr != "") {
			//空白で分割
			$wordArray = explode(" ", $wordStr);

			for ($i = 0; $i < count($wordArray); $i++) {
                if ($wordArray[$i] != "") {
                    $word = $wordArray[$i];
                    //タグかどうか
                    if (mb_substr($word, 0, 1) == "#") {
                        //タグ
                        $word = mb_substr($word, 1);
                        $wordSql = $wordSql . "(CONCAT(',', `posts`.`tags`, ',') LIKE '%," . $word . ",%') AND ";
                    } else {
                        //タグじゃない
                        $wordSql = $wordSql . "(
                            `posts`.`title` LIKE '%" . $wordArray[$i] . "%' OR 
                            `posts`.`outline` LIKE '%" . $wordArray[$i] . "%' OR 
                            `posts`.`tags` LIKE '%" . $wordArray[$i] . "%' OR 
                            `users`.`user_name` LIKE '%" . $wordArray[$i] . "%' OR 
                            `posts`.`series` LIKE '%" . $wordArray[$i] . "%' ) AND ";        
                    }
                }
			}
            $sql = $sql . $wordSql;
		}
		// ^(?=.*R\-18)(?!.*R\-18G).*$ R-18かつR-18Gじゃない正規表現byうどん

		//ユーザー設定：ミュートタグ
		$muteSql = "";
		if ($muteStr != "") {
			//カンマで分割
			$muteArray = explode(",", $muteStr);

			for ($i = 0; $i < count($muteArray); $i++) {
				if ($muteArray[$i] == "R-18") {
					$muteSql = $muteSql . "(`posts`.`rating` != 1) AND ";
				} else if ($muteArray[$i] == "R-18G") {
					$muteSql = $muteSql . "(`posts`.`rating` != 2) AND ";
				} else {
					$muteSql = $muteSql . "(CONCAT(',', `posts`.`tags`, ',') NOT LIKE '%," . $muteArray[$i] . ",%') AND ";		
				}
			}
            $sql = $sql . $muteSql;
		}

        $sql = $sql . " 1 = 1 ";

        //件数を先に取得
        if ($isCount) {
            return "SELECT count(*) as abc " . $sql;
        } else {
            //並べ替え
            if ($fOrder == 0) {
                $sql = $sql . "ORDER BY `posts`.`created_at` DESC ";
            } else if ($fOrder == 1) {
                $sql = $sql . "ORDER BY `posts`.`created_at` asc ";
            } else if ($fOrder == 2) {
                $sql = $sql . "ORDER BY `book` DESC, `posts`.`created_at` DESC ";
            }            
            return $sqlInit . $sql;
        }
        // dd($countSql);
    }

    //
    //投稿一覧取得（自分）
    //
    public function getMyPostList(Request $request): JsonResponse {
        $postList = \DB::select('
            SELECT
             posts.post_id,
             posts.user_id,
             posts.title,
             posts.outline,
             char_length(posts.body) AS length,
             posts.series,
             posts.rating,
             posts.chara,
             posts.creation,
             posts.tags,
             posts.filter,
             posts.publishing,
             posts.view_count,
             posts.created_at,
             IFNULL(bookmarks.bookmark_count, 0) AS bookmark_count,
             IFNULL(stamps.stamp_count, 0) AS stamp_count 
            FROM
             posts
            LEFT JOIN (SELECT post_id, count(*) AS bookmark_count FROM bookmarks GROUP BY post_id) AS bookmarks
             ON posts.post_id = bookmarks.post_id
            LEFT JOIN (select post_id, count(*) AS stamp_count  FROM stamps GROUP BY post_id) AS stamps
             ON posts.post_id = stamps.post_id
            WHERE posts.user_id = "' . Auth::User()->user_id . '"
            ORDER BY posts.created_at DESC
        ');

        return response()->json(['status' => Consts::API_SUCCESS, 'postList' => $postList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //本棚一覧取得
    //
    public function getBookmarkList(Request $request): JsonResponse {
        $bookmarkList = \DB::select('
            SELECT
             bookmarks.post_id,
             users.user_name,
             posts.user_id,
             posts.title,
             posts.outline,
             char_length(posts.body) AS len,
             posts.series,
             posts.rating,
             posts.chara,
             posts.creation,
             posts.tags,
             posts.filter,
             posts.publishing,
             posts.view_count,
             posts.created_at
            FROM
             bookmarks
            LEFT JOIN posts
             ON posts.post_id = bookmarks.post_id
            LEFT JOIN users
             ON posts.user_id = users.user_id
            WHERE bookmarks.user_id = "' . Auth::User()->user_id . '" AND posts.publishing != 99
            ORDER BY posts.created_at DESC
        ');

        return response()->json(['status' => Consts::API_SUCCESS, 'bookmarkList' => $bookmarkList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //フォロー一覧取得
    //
    public function getFollowList(Request $request): JsonResponse {
        $followList = \DB::select('
            SELECT
            follows.follow_id,
            users.user_name,
            users.profile
            FROM
            follows
            LEFT JOIN users
            ON follows.follow_id = users.user_id
            WHERE follows.user_id = "' . Auth::User()->user_id . '"
            ORDER BY users.created_at DESC
        ');

        return response()->json(['status' => Consts::API_SUCCESS, 'followList' => $followList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //新規投稿
    //
    public function insertPost(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'title' => 'required|max:50',
                'outline' => 'required|max:200',
                'body' => 'required|max:20000',
                'series' => 'required|max:50',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }
        
        $userId = Auth::User()->user_id;
        $title = $request->title;
        $outline = str_replace("　", " ", $request->outline);
        $body = $request->body;
        $conversion = $request->conversion;
        $series = $request->series;
        $rating = $request->rating;
        $chara = $request->chara;
        $creation = $request->creation;
        $tags = $request->tags;
        $filter = $request->filter;
        $publishing = $request->publishing;
        $publishingSub1 = $request->publishingSub1;
        $publishingSub2 = $request->publishingSub2;
        $searchable = $request->searchable;
        
        if ($searchable == "true") {
            $searchable = 1;
        } else {
            $searchable = 0;
        }
        
        //表現規制チェック
        if (!$this->checkRegulation($title) || !$this->checkRegulation($outline) || !$this->checkRegulation($body) || !$this->checkRegulation($series)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
        }

        //異常値チェック
        if (($rating != 0 && $rating != 1 && $rating != 2 && $rating != 3) || 
        ($chara != 0 && $chara != 1 && $chara != 2) ||
        ($creation != 0 && $creation != 1) ||
        ($filter != 0 && $filter != 1 && $filter != 2 && $filter != 3 && $filter != 4 && $filter != 5) ||
        ($publishing != 0 && $publishing != 1 && $publishing != 2 && $publishing != 3 && $publishing != 4 && $publishing != 99) ||
        ($searchable != 0 && $searchable != 1)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
        }

        //公開方法別チェック
        if ($publishing == 1) {
            //パスワード
            try {
                $validatedData = $request->validate([
                    'publishingSub1' => 'required|max:20',
                    'publishingSub2' => 'required|max:50',
                ]);
            } catch (ValidationException $e) {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
            }
        } else if ($publishing == 4) {
            //リスト
            try {
                $validatedData = $request->validate([
                    'publishingSub1' => 'required|max:200',
                ]);
            } catch (ValidationException $e) {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
            }
            $publishingSub2 = "";
        } else {
            //それ以外　（リツイートは後で）
            $publishingSub1 = "";
            $publishingSub2 = "";
        }

        //変換項目チェック
        if ($conversion != "") {
            $conversionArray = explode(Consts::CONVERSION2, $conversion);
            if (count($conversionArray) > 4) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            for ($i=0; $i < count($conversionArray); $i++) { 
                $con = explode(Consts::CONVERSION1, $conversionArray[$i]);
                if ($con[0] == "" || $con[1] == "" || mb_strlen($con[0]) > 10 || mb_strlen($con[1]) > 20) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($con[0]) || !$this->checkRegulation($con[1])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }
        }

        //ジャンルタグチェック
        $tagStr = "";
        if ($tags != "") {
            $tagArray = explode(',', $tags);
            if (count($tagArray) > 8) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            for ($i=0; $i < count($tagArray); $i++) { 
                if (mb_strlen($tagArray[$i]) > 21) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($tagArray[$i])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }
        }

        //post_id生成
        $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $postId = "";
        while ($postId == "") {
            for ($i = 0; $i < 20; $i++) {
                $ch = substr($randomStr, mt_rand(0, strlen($randomStr)) - 1, 1);
                $postId = $postId . $ch;
            }

            //重複チェック
            $checkData = DB::table('posts')->where('post_id', $postId)->first();

            if ($checkData != null) {
                $postId = "";
            }
        }

        //新規データ生成
        $postData = new post;
        $postData->post_id = $postId;
        $postData->user_id = Auth::User()->user_id;
        $postData->title = $title;
        $postData->outline = $outline;
        $postData->body = $body;
        $postData->conversion = $conversion;
        $postData->series = $series;
        $postData->chara = $chara;
        $postData->rating = $rating;
        $postData->creation = $creation;
        $postData->tags = $tags;
        $postData->filter = $filter;
        $postData->publishing = $publishing;
        $postData->publishing_sub1 = $publishingSub1;
        $postData->publishing_sub2 = $publishingSub2;
        $postData->searchable = $searchable;
        $postData->save();

        //twitterカード更新
        $this->makeTwitterCard($postData);

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo(), 'postId' => $postId]);
    }

    //
    //投稿更新
    //
    public function updatePost(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required'],
                'title' => ['required'],
                'outline' => ['required'],
                'body' => ['required'],
                'series' => ['required'],
           ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $postId = $request->postId;
        $title = $request->title;
        $outline = str_replace("　", " ", $request->outline);
        $body = $request->body;
        $conversion = $request->conversion;
        $series = $request->series;
        $rating = $request->rating;
        $chara = $request->chara;
        $creation = $request->creation;
        $tags = $request->tags;
        $filter = $request->filter;
        $publishing = $request->publishing;
        $publishingSub1 = $request->publishingSub1;
        $publishingSub2 = $request->publishingSub2;
        $searchable = $request->searchable;
        
        if ($searchable == "true") {
            $searchable = 1;
        } else {
            $searchable = 0;
        }

        //表現規制チェック
        if (!$this->checkRegulation($title) || !$this->checkRegulation($outline) || !$this->checkRegulation($body) || !$this->checkRegulation($series)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
        }
        
        //異常値チェック
        if (($rating != 0 && $rating != 1 && $rating != 2 && $rating != 3) || 
        ($chara != 0 && $chara != 1 && $chara != 2) ||
        ($creation != 0 && $creation != 1) ||
        ($filter != 0 && $filter != 1 && $filter != 2 && $filter != 3 && $filter != 4 && $filter != 5) ||
        ($publishing != 0 && $publishing != 1 && $publishing != 2 && $publishing != 3 && $publishing != 4 && $publishing != 99) ||
        ($searchable != 0 && $searchable != 1)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
        }

        //公開方法別チェック
        if ($publishing == 1) {
            //パスワード
            try {
                $validatedData = $request->validate([
                    'publishingSub1' => 'required|max:200',
                    'publishingSub2' => 'required|max:200',
                ]);
            } catch (ValidationException $e) {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
            }
        } else if ($publishing == 4) {
            //リスト
            try {
                $validatedData = $request->validate([
                    'publishingSub1' => 'required|max:200',
                ]);
            } catch (ValidationException $e) {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
            }
            $publishingSub2 = "";
        } else {
            //それ以外　（リツイートは後で）
            $publishingSub1 = "";
            $publishingSub2 = "";
        }

        //変換項目チェック
        if ($conversion != "") {
            $conversionArray = explode(Consts::CONVERSION2, $conversion);
            if (count($conversionArray) > 4) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            
            for ($i=0; $i < count($conversionArray); $i++) { 
                $con = explode(Consts::CONVERSION1, $conversionArray[$i]);
                if ($con[0] == "" || $con[1] == "" || mb_strlen($con[0]) > 10 || mb_strlen($con[1]) > 20) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($con[0]) || !$this->checkRegulation($con[1])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }    
        }

        //ジャンルタグチェック
        if ($tags != "") {
            $tagArray = explode(',', $tags);
            if (count($tagArray) > 8) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            for ($i=0; $i < count($tagArray); $i++) { 
                if (mb_strlen($tagArray[$i]) > 20) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($tagArray[$i])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }
        }

        //既存データ取得
        $postData = post::where('post_id', $postId)->first();

        if ($postData == null) {
            //該当なし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => $request->postId]);
        }


        //情報を更新
        $postData->title = $title;
        $postData->outline = $outline;
        $postData->body = $body;
        $postData->conversion = $conversion;
        $postData->series = $series;
        $postData->rating = $rating;
        $postData->chara = $chara;
        $postData->creation = $creation;
        $postData->tags = $tags;
        $postData->filter = $filter;
        $postData->publishing = $publishing;
        $postData->publishing_sub1 = $publishingSub1;
        $postData->publishing_sub2 = $publishingSub2;
        $postData->searchable = $searchable;
        $postData->save();

        //twitterカード更新
        $this->makeTwitterCard($postData);

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //Twitterカード生成
    //
    public function makeTwitterCard($postData) {
        $cardBase = new Imagick(realpath("./") . '/app/img/card_base.jpg');

        $draw = new ImagickDraw();

        $titleStr = $postData->title;
        $outlineStr = $postData->outline;

        $tLen = 25;
        $tPosiY = 40;
        $oLen = 30;
        
        if (mb_strlen($titleStr) > $tLen) {
            $title = mb_substr($titleStr, 0, $tLen) . "\n" . mb_substr($titleStr, $tLen);
            $tPosiY = 30;
        } else {
            $title = $titleStr;
        }

        $outline = "";
        $c = 0;
        while ($outlineStr != "") {
            if (mb_strlen($outlineStr) > $oLen) {
                if ($outline == "") {
                    $outline = mb_substr($outlineStr, 0, $oLen);
                } else {
                    $outline = $outline . "\n" . mb_substr($outlineStr, 0, $oLen);
                }
                $outlineStr = mb_substr($outlineStr, $oLen);
            } else {
                if ($outline == "") {
                    $outline = $outlineStr;
                } else {
                    $outline = $outline . "\n" . $outlineStr;
                }
                $outlineStr = "";
            }
        }

        $draw->setFont(realpath("./") . "/app/font.otf");
        $draw->setFontSize(18);
        $draw->setFillColor("black");
        $draw->setTextInterlineSpacing(5);
        $cardBase->annotateImage($draw, 130, $tPosiY, 0, $title);
        $cardBase->annotateImage($draw, 70, 130, 0, $outline);
        
        $cardBase->writeImage(realpath("./") . '/storage/card/card_' . $postData->post_id . '.jpg');
    }


    //
    //ユーザーページ － ユーザー情報取得
    //
    public function getAuthorInfo(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'targetId' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $userId = Auth::User()->user_id;
        $targetId = $request->targetId;
        $muteStr = Auth::User()->mute_tag;

        $retArray = array();
        
        //対象のデータ取得
        $targetUserData = user::where('user_id', $targetId)->first();

        if ($targetUserData == null) {
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => ""]);
        }
        $retArray = $retArray + array('user_name' => $targetUserData->user_name);
        $retArray = $retArray + array('user_profile' => $targetUserData->profile);

        $twitterCode = $targetUserData->twitter_code;
        if ($targetUserData->show_twitter == 0 || $targetUserData->twitter_code == "") {
            $twitterCode = "";
        }
        $retArray = $retArray + array('twitter_code' => $twitterCode);
        
        //対象をフォローしているか
        $followData = follow::where('user_id', $userId)->where('follow_id', $targetId)->first();
        if ($followData != null) {
            $retArray = $retArray + array('follow' => 1);
        } else {
            $retArray = $retArray + array('follow' => 0);
        }

        //対象をミュートしているか
        $muteData = mute::where('user_id', $userId)->where('mute_id', $targetId)->first();
        if ($muteData != null) {
            $retArray = $retArray + array('mute' => 1);
        } else {
            $retArray = $retArray + array('mute' => 0);
        }

        $sql = "SELECT
            `posts`.`post_id`,
            `posts`.`title`,
            `posts`.`outline`,
            char_length(`posts`.`body`) AS len,
            `posts`.`series`,
            `posts`.`rating`,
            `posts`.`chara`,
            `posts`.`creation`,
            `posts`.`tags`,
            `posts`.`filter`,
            `posts`.`publishing`,
            `posts`.`view_count`,
            `posts`.`created_at`
            from
            `posts`
            LEFT JOIN `users`
            ON `posts`.`user_id` = `users`.`user_id`
            WHERE `posts`.`user_id` = '" . $targetId . "' AND `posts`.`publishing` != 99 AND ";

        //ユーザー設定：ミュートタグ
        $muteSql = "";
        if ($muteStr != "") {
            //カンマで分割
            $muteArray = explode(",", $muteStr);

            for ($i = 0; $i < count($muteArray); $i++) {
                if ($muteArray[$i] == "R-18") {
                    $muteSql = $muteSql . "(`posts`.`rating` != 1) AND ";
                } else if ($muteArray[$i] == "R-18G") {
                    $muteSql = $muteSql . "(`posts`.`rating` != 2) AND ";
                } else {
                    $muteSql = $muteSql . "(
                        `posts`.`title` NOT LIKE '%" . $muteArray[$i] . "%' AND 
                        `posts`.`outline` NOT LIKE '%" . $muteArray[$i] . "%' AND 
                        `posts`.`tags` NOT LIKE '%" . $muteArray[$i] . "%' AND 
                        `users`.`user_name` NOT LIKE '%" . $muteArray[$i] . "%' AND 
                        `posts`.`series` NOT LIKE '%" . $muteArray[$i] . "%' ) AND ";		
                }
            }

            $sql = $sql . $muteSql;
        }

        $sql = $sql . " 1 = 1 ORDER BY `posts`.`created_at` DESC";
        $retArray = $retArray + array('sql' => $sql);

        $postList = \DB::select($sql);

        //リンクの一覧取得
        $linkArray = array();
        if ($targetUserData->link_0_0 != "") {
            array_push($linkArray, [$targetUserData->link_0_0, $targetUserData->link_0_1]);
        }
        if ($targetUserData->link_1_0 != "") {
            array_push($linkArray, [$targetUserData->link_1_0, $targetUserData->link_1_1]);
        }
        if ($targetUserData->link_2_0 != "") {
            array_push($linkArray, [$targetUserData->link_2_0, $targetUserData->link_2_1]);
        }
        if ($targetUserData->link_3_0 != "") {
            array_push($linkArray, [$targetUserData->link_3_0, $targetUserData->link_3_1]);
        }

        $retArray = $retArray + array('postList' => $postList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());
        $retArray = $retArray + array('linkList' => $linkArray);

        return response()->json($retArray);
    }

    //
    //ユーザーページ － フォロー情報更新
    //
    public function updateFollow(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'followId' => ['required'],
                'value' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $followId = $request->followId;
        $value = $request->value;

        if ($value == 1) {
            //追加
            $data = new Follow;
            $data->user_id = Auth::User()->user_id;
            $data->follow_id = $followId;
            $data->save();
        } else {
            //削除
            $data = Follow::where('user_id', Auth::User()->user_id)
            ->where('follow_id', $followId)
            ->delete();
        }
        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //ユーザーページ － ミュート情報更新
    //
    public function updateMute(Request $request): JsonResponse {
        $muteId = $request->muteId;
        $value = $request->value;

        //入力チェック
        try {
            $validatedData = $request->validate([
                'muteId' => ['required'],
                'value' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        if ($value == 1) {
            //追加
            $data = new Mute;
            $data->user_id = Auth::User()->user_id;
            $data->mute_id = $muteId;
            $data->save();
        } else {
            //削除
            $data = Mute::where('user_id', Auth::User()->user_id)
            ->where('mute_id', $muteId)
            ->delete();
        }
        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － 設定情報取得
    //
    public function getSettingInfo(Request $request): JsonResponse {
        $muteList = mute::LEFTJOIN('users', 'mutes.mute_id', '=', 'users.user_id')->where('mutes.user_id', Auth::User()->user_id)
        ->get(['mutes.mute_id', 'users.user_name']);

        //リンクの一覧取得
        $userData = Auth::User();
        $linkArray = array();
        if ($userData->link_0_0 != "") {
            array_push($linkArray, [$userData->link_0_0, $userData->link_0_1]);
        }
        if ($userData->link_1_0 != "") {
            array_push($linkArray, [$userData->link_1_0, $userData->link_1_1]);
        }
        if ($userData->link_2_0 != "") {
            array_push($linkArray, [$userData->link_2_0, $userData->link_2_1]);
        }
        if ($userData->link_3_0 != "") {
            array_push($linkArray, [$userData->link_3_0, $userData->link_3_1]);
        }
        if(count($linkArray) == 0) {
            array_push($linkArray, ["", ""]);
        }

        return response()->json(['status' => Consts::API_SUCCESS, 'muteList' => $muteList, 'baseInfo' => $this->retUserInfo(), 'linkList' => $linkArray]);
    }

    //
    //設定 － 基本情報更新
    //
    public function updateSettingBase(Request $request): JsonResponse {
        $file = $request->file('iconFile');

        //入力チェック
        try {
            $validateArray = [
                'userName' => 'required|max:10',
                'profile' => 'required|max:400',
                'link00' => 'max:20',
                'link01' => 'max:200',
                'link10' => 'max:20',
                'link11' => 'max:200',
                'link20' => 'max:20',
                'link21' => 'max:200',
                'link30' => 'max:20',
                'link31' => 'max:200',
            ];
    
            if ($file != null) {
                //アイコン添付あり
                $validateArray += array('iconFile' => 'max:1024|mimes:jpg,jpeg,png');
            }

            $validatedData = $request->validate($validateArray);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $userName = $request->userName;
        $profile = $request->profile;
        // $file = $request->file('iconFile']['tmp_name'];
        $file = $request->file('iconFile');
        $fileName = $request->iconFileName;

        //表現規制チェック
        if (!$this->checkRegulation($userName) || !$this->checkRegulation($profile)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
        }

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->user_name = $userName;
        $userData->profile = $profile;
        $userData->link_0_0 = $request->link00;
        $userData->link_0_1 = $request->link01;
        $userData->link_1_0 = $request->link10;
        $userData->link_1_1 = $request->link11;
        $userData->link_2_0 = $request->link20;
        $userData->link_2_1 = $request->link21;
        $userData->link_3_0 = $request->link30;
        $userData->link_3_1 = $request->link31;
        // $userData->updated_at = date("Y/m/d H:i:s");

        try {
            DB::beginTransaction();
            
            $flg = $userData->save();

            if ($flg) {
                //アイコンファイルがある場合は更新
                if ($file != null) {
                    $filePath = $_FILES["iconFile"]["tmp_name"];
                    $icon = new Imagick($filePath);
                    $iW = $icon->getImageWidth();
                    $iH = $icon->getImageHeight();
                    $iSize = $iW;
                    if ($iW > $iH) {
                        $iSize = $iH;
                    }
                    $icon->cropThumbnailImage($iSize, $iSize);            
                    $icon->sampleImage(200, 200);
                    $icon->writeImage('./storage/icon/' . $userData->user_id . ".png");
                } else if ($fileName == "DEFAULT") {
                    copy('./app/img/icon/icon_default.png', '../storage/app/public/icon/' . $userData->user_id . '.png');
                }

                DB::commit();
                
                return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $userData]);

            } else {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '']);                    
            }
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '']);                
        }
    }

    //
    //設定 － お気に入りタグ更新
    //
    public function updateSettingFavorite(Request $request): JsonResponse {
        $favorite = $request->favorite;

        //タグチェック
        if ($favorite != "") {
            $tagArray = explode(',', $favorite);
            if (count($tagArray) > 8) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            for ($i=0; $i < count($tagArray); $i++) { 
                if (mb_strlen($tagArray[$i]) > 20) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($tagArray[$i])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }
        }

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->favorite_tag = $favorite;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->update();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － ミュートタグ更新
    //
    public function updateSettingMute(Request $request): JsonResponse {
        $mute = $request->mute;

        //タグチェック
        if ($mute != "") {
            $tagArray = explode(',', $mute);
            if (count($tagArray) > 8) {
                //異常値エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
            }
            for ($i=0; $i < count($tagArray); $i++) { 
                if (mb_strlen($tagArray[$i]) > 20) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } else if (!$this->checkRegulation($tagArray[$i])) {
                    //異常値エラー
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => ""]);
                } 
            }
        }

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->mute_tag = $mute;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->update();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － Twitterアカウント表示更新
    //
    public function updateSettingShowTwitter(Request $request): JsonResponse {
        $showTwitter = $request->showTwitter;
        
        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->show_twitter = $showTwitter;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->save();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － お知らせ
    //
    public function updateSettingReceiveInfo(Request $request): JsonResponse {
        $val = $request->receiveInfo;

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->receive_info = $val;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->save();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － 閲覧制限更新
    //
    public function updateSettingRestrictions(Request $request): JsonResponse {
        $kbn = $request->kbn;
        $param = $request->param;

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        if ($kbn == "R18") {
            $userData->disp_r18 = $param;
        } else {
            $userData->disp_r18g = $param;
        }
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->save();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo(), 'param' => $param]);
    }

    //
    //設定 － 退会
    //
    public function deleteUser(Request $request): JsonResponse {

    }

    //
    //読書用投稿取得
    //
    public function getReadingData(Request $request): JsonResponse {
        $retArray = array();

        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $postId = $request->postId;

        $postData = \DB::select('
            SELECT
            posts.post_id,
            posts.user_id,
            users.user_name,
            posts.title,
            posts.outline,
            posts.body,
            char_length(posts.body) AS len,
            posts.series,
            posts.rating,
            posts.chara,
            posts.creation,
            posts.tags,
            posts.filter,
            posts.publishing,
            posts.publishing_sub1,
            posts.publishing_sub2,
            posts.created_at,
            posts.conversion,
            CASE WHEN bookmarks.user_id IS NULL THEN 0 ELSE 1 END AS bookmark
            from
            posts
            LEFT JOIN users
            ON posts.user_id = users.user_id
            LEFT JOIN (select * FROM bookmarks WHERE post_id = "' . $postId .'" AND user_id = "' . Auth::User()->user_id .'") AS bookmarks
            ON posts.post_id = bookmarks.post_id
            where
            posts.post_id = "' . $postId . '"
        ');

        if ($postData == null) {
            //データがない場合
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => '該当データなし']);
        }

        $postData = $postData[0];

        //公開方法別処理
        if ($postData->publishing == "1") {
            //答え空白
            $postData->publishing_sub1 = "";
        } else if ($postData->publishing == "4") {
            //リスト空白
            $postData->publishing_sub1 = "";
        } else if ($postData->publishing == "99") {
            //非公開の場合
            return response()->json(['status' => Consts::API_FAILED_PRIVATE, 'errMsg' => '非公開データ']);
        }

        //本文の扱い
        if ($postData->user_id != auth::User()->user_id && $postData->publishing != "0") {
            //自分のデータ　または　全体公開　じゃない場合
            $postData->body = "";
        }

        $retArray = $retArray + array('postData' => $postData);

        //スタンプ情報を取得
        $stampList = Stamp::where('post_id', $postId)->get(['stamp_id']);

        $retArray = $retArray + array('stamp' => $stampList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());
        return response()->json($retArray);
    }

    //
    //本棚更新
    //
    public function updateBookmark(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required'],
                'value' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $postId = $request->postId;
        $value = $request->value;

        if ($value == 1) {
            //追加
            $data = new Bookmark;
            $data->user_id = Auth::user()->user_id;
            $data->post_id = $postId;
            $data->save();
        } else {
            //削除
            $data = Bookmark::where('user_id', Auth::User()->user_id)
            ->where('post_id', $postId)
            ->delete();
        }
        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //スタンプ追加
    //
    public function addStamp(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required'],
                'userId' => ['required'],
                'stamp' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        //スタンプの上限確認
        $stampList = Stamp::where('post_id', $request->postId)->where('user_id', $request->userId)->get();

        if (count($stampList) >= 10) {
            //スタンプ上限
            return response()->json(['status' => Consts::API_FAILED_LIMIT, 'errMsg' => ""]);
        }
        // dd(count($stampList));
        DB::table('stamps')->insert([
            'post_id' => $request->postId,
            'user_id' => $request->userId,
            'stamp_id' => $request->stamp
        ]);

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo(), 'count' => count($stampList) + 1]);
    }

    //
    //編集 － 投稿情報取得（自分）
    //
    public function getMyPostData(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }
        
        //投稿データ取得
        $postData = \DB::select('
            SELECT
            posts.title,
            posts.post_id,
            posts.outline,
            posts.body,
            posts.conversion,
            posts.series,
            posts.rating,
            posts.chara,
            posts.creation,
            posts.tags,
            posts.filter,
            posts.publishing,
            posts.publishing_sub1,
            posts.publishing_sub2,
            posts.searchable 
            from
            posts
            WHERE posts.post_id = "' . $request->postId . '"
        ');
        if ($postData == null || count($postData) == 0) {
            //データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA]);
        }

        $postData = $postData[0];

        //公開方法が「リスト限定」の場合、Twitterリストを取得する
        $twitterList = null;
        if ($postData->publishing == 4 && Auth::User()->twitter_id != "") {
            //リストの取得
            $twitterList = $this->retTwitterData("TWITTER_LIST", Auth::User()->twitter_id);
        }

        //シリーズ一覧取得
        $seriesList = array("指定なし");
        $sList = post::where('user_id', auth::User()->user_id)->orderby('created_at', 'desc')->get(['series']);
        foreach ($sList as $data) {
            $flg = false;
            $val = $data["series"];
            for ($i=0; $i < count($seriesList); $i++) { 
                if ($val == $seriesList[$i]) {
                    $flg = true;
                }
            }
            if (!$flg) {
                array_push($seriesList, $val);
            }
        }

        return response()->json(['status' => Consts::API_SUCCESS, 'postData' => $postData, 'seriesList' => $seriesList, 'twitterList' => $twitterList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //編集 － 投稿削除（自分）
    //
    public function deletePost(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'postId' => ['required']
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        $postId = $request->postId;

        //削除
        $data = Post::where('post_id', $postId)->delete();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //お問い合わせ
    //
    public function contact(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'name' => 'required',
                'mailAddress' => 'required|max:200|email:strict,dns,spoof|string',
                'phoneNumber' => 'numeric',
                'body' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        } 

        //メール本文作成
        $mailBody = "お名前：" . $request->name . "\r\n";
        $mailBody = $mailBody . "メールアドレス：" . $request->mailAddress . "\r\n";
        $mailBody = $mailBody . "電話番号：" . $request->phoneNumber . "\r\n";
        $mailBody = $mailBody . "内容：" . $request->body . "\r\n";
        $mailBody = $mailBody . "日時：" . date("Y-m-d H:i:s") . "\r\n";
        
        try {
            Mail::send(new MailMgr("お問い合わせ", $mailBody, "saga.siga.noga@gmail.com"));
        } catch (Exception $e) {
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'リセット申請エラー']);
        }
        return response()->json(['status' => Consts::API_SUCCESS]); 
    }

    //
    //Twitter情報取得
    //
    public function getTwitterInfo(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'kbn' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }

        //取得情報別処理
        if ($request->kbn == "TWITTER_LIST") {
            $twitterList = $this->retTwitterData($request->kbn, Auth::User()->twitter_id);
            return response()->json(['twitterList' => $twitterList]);
        }
    }

    public function retTwitterData($kbn, $param) {
        $api_key = Consts::TWITTER_API_KEY;		// APIキー
        $api_secret = Consts::TWITTER_API_SECRET;		// APIシークレット

        $userData = Auth::User();
        $access_token = $userData->twitter_token;		// アクセストークン
        $access_token_secret = $userData->twitter_token_secret;		// アクセストークンシークレット   

        $twObj = new TwitterOAuth($api_key,$api_secret,$access_token,$access_token_secret);

        $retList = array();
        if ($kbn == "TWITTER_LIST") {
            //公開リストの取得
            $apiData = $twObj->get("lists/list", ["user_id" => $param]);
            if (gettype($apiData) == "array") {
                //配列の場合
                $list = array();
                foreach ($apiData as $data) {
                    array_push($list, ['id' => "$data->id", 'name' => $data->name]);
                }
                $retList += array('status' => '1');
                $retList += array('list' => $list);
                return $retList;    
            } else if (gettype($apiData) == "object") {
                //オブジェクトの場合　データ取得上限
                $retList += array('status' => '0');
                $retList += array('list' => null);
                return $retList;
            }
        } else if ($kbn == "TWITTER_LIST_MEMBER") {
            //公開リストの取得
            $apiData = $twObj->get("lists/members/show", ["list_id" => $param, "user_id" => Auth::User()->twitter_id]);
            if (property_exists($apiData, 'errors')) {
                if ($apiData->errors[0]->code == '109') {
                    //メンバーじゃない
                    return "L1";
                } else if ($apiData->errors[0]->code == '34') {
                    //リストなし
                    return "L2";
                } else if ($apiData->errors[0]->code == '88') {
                    //取得上限
                    return "99";
                }
            } else {
                //メンバー
                return "1";
            }
        } else if ($kbn == "FRIENDSHIP") {
            $apiData = $twObj->get("friendships/lookup", ["user_id" => $param]);
            if (property_exists($apiData, 'errors')) {
                return null;
            }
            $retList = [0, 0];
            foreach ($apiData as $data) {
                $connections = $data->connections;
                foreach ($connections as $val) {
                    if ($val == "following") {
                        $retList[0] = 1;
                    } else if ($val == "followed_by") {
                        $retList[1] = 1;
                    }
                }
            }
            return $retList;
        }
    }

    // 
    // 公開方法別の照合
    //
    public function checkPublishing(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'kbn' => 'required',
                'postId' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }
        
        $kbn = $request->kbn;
        $postId = $request->postId;

        $retList = array();

        $postData =  post::where('post_id', $postId)->first();

        if ($postData == null) {
            //データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => ""]);
        } else if ($postData->pupblishing == "99") {
            //非公開
            return response()->json(['status' => Consts::API_FAILED_PRIVATE, 'errMsg' => ""]);
        }
        
        if (($postData->publishing == "1" && $kbn != "PASSWORD") || ($postData->publishing == "2" && $kbn != "TWITTER_SOUGO") || 
        ($postData->publishing == "3" && $kbn != "TWITTER_FOLLOW") || ($postData->publishing == "4" && $kbn != "TWITTER_LIST")) {
            //パラメータと公開方法が違う場合
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
        }
        //公開方法別処理
        if ($kbn == "PASSWORD") {
            //パスワード
            //追加バリデート
            try {
                $validatedData = $request->validate([
                    'password' => 'required',
                ]);
            } catch (ValidationException $e) {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
            }
            $password = $request->password;

            if ($postData->publishing_sub1 == $password) {
                //正解
                $retList += array('status' => Consts::API_SUCCESS);
                $retList += array('body' => $postData->body);
            } else {
                //不正解
                $retList += array('status' => Consts::API_FAILED_PUBLISHING);
                $retList += array('error' => "P1");
            }
        } else if ($kbn == "TWITTER_SOUGO" || $kbn == "TWITTER_FOLLOW" || $kbn == "TWITTER_LIST") {
            //twitter連携
            $authorUserId = $postData->user_id;
            $authorData = user::where('user_id', $authorUserId)->first();

            if ($authorData == null) {
                //著者データなし
                return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => "NULL_AUTHOR"]);
            } else if ($authorData->twitter_id == "") {
                //著者Twitter非認証
                return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => "AUTH_AUTHOR"]);
            }

            if ($kbn == "TWITTER_SOUGO" || $kbn == "TWITTER_FOLLOW") {
                //追加バリデート
                try {
                    $validatedData = $request->validate([
                        'userId' => 'required',
                    ]);
                } catch (ValidationException $e) {
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'errMsg' => $e->getMessage()]);
                }
                $userId = $request->userId;
                $userData =  user::where('user_id', $userId)->first();
                
                if ($userData == null) {
                    //自ユーザなし
                    return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => "NULL"]);
                } else if ($userData->twitter_id == "") {
                    //自ユーザーTwitter非認証
                    return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => "AUTH"]);
                }

                $targetTwitterId = $userData->twitter_id;

                //フォロー情報取得
                $friendShip = $this->retTwitterData("FRIENDSHIP", $targetTwitterId);
                if ($friendShip == null) {
                    //取得上限
                    $retList += array('status' => Consts::API_FAILED_PUBLISHING);
                    $retList += array('error' => "99");
                } else {
                    if ($kbn == "TWITTER_SOUGO") {
                        //相互フォロー
                        if ($friendShip[0] == 1 && $friendShip[1] == 1) {
                            //OK
                            $retList += array('status' => Consts::API_SUCCESS);
                            $retList += array('body' => $postData->body);
                        } else {
                            //NG
                            $retList += array('status' => Consts::API_FAILED_PUBLISHING);
                            $retList += array('error' => "S1");
                        }
                    } else if ($kbn == "TWITTER_FOLLOW") {
                        //フォロワー
                        if ($friendShip[0] == 1) {
                            //OK
                            $retList += array('status' => Consts::API_SUCCESS);
                            $retList += array('body' => $postData->body);
                        } else {
                            //NG
                            $retList += array('status' => Consts::API_FAILED_PUBLISHING);
                            $retList += array('error' => "F1");
                        }
                    }
                }
            } else if ($kbn == "TWITTER_LIST") {
                //公開リスト
                $listId = $postData->publishing_sub1;
                $isMember = $this->retTwitterData('TWITTER_LIST_MEMBER', $listId);
                if ($isMember == "1") {
                    //OK
                    $retList += array('status' => Consts::API_SUCCESS);
                    $retList += array('body' => $postData->body);
                } else {
                    //NG
                    $retList += array('status' => Consts::API_FAILED_PUBLISHING);
                    $retList += array('error' => $isMember);
                }
            }
        }
        $retList += array('baseInfo' => $this->retUserInfo());
        return response()->json($retList);
    }

    public function test(Request $request): JsonResponse {
        $cardBase = new Imagick(realpath("./") . '/app/img/card_base.jpg');

        $draw = new ImagickDraw();

        $titleStr = "あいうえおかきくけこあいうえおかきく";
        $outlineStr = "あいうえおかきくけおあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけこあいうえおかきくけそ";

        $tLen = 25;
        $tPosiY = 40;
        $oLen = 30;
        
        if (mb_strlen($titleStr) > $tLen) {
            $title = mb_substr($titleStr, 0, $tLen) . "\n" . mb_substr($titleStr, $tLen);
            $tPosiY = 30;
        } else {
            $title = $titleStr;
        }

        $outline = "";
        $c = 0;
        while ($outlineStr != "") {
            if (mb_strlen($outlineStr) > $oLen) {
                if ($outline == "") {
                    $outline = mb_substr($outlineStr, 0, $oLen);
                } else {
                    $outline = $outline . "\n" . mb_substr($outlineStr, 0, $oLen);
                }
                $outlineStr = mb_substr($outlineStr, $oLen);
            } else {
                if ($outline == "") {
                    $outline = $outlineStr;
                } else {
                    $outline = $outline . "\n" . $outlineStr;
                }
                $outlineStr = "";
            }
        }

        $draw->setFont(realpath("./") . "/app/font.otf");
        $draw->setFontSize(18);
        $draw->setFillColor("black");
        $draw->setTextInterlineSpacing(5);
        $cardBase->annotateImage($draw, 130, $tPosiY, 0, $title);
        $cardBase->annotateImage($draw, 70, 130, 0, $outline);

        // $filePath = $_FILES["iconFile"]["tmp_name"];
        // $icon = new Imagick($filePath);
        // $iW = $icon->getImageWidth();
        // $iH = $icon->getImageHeight();
        // $iSize = $iW;
        // if ($iW > $iH) {
        //     $iSize = $iH;
        // }
        // $icon->cropThumbnailImage($iSize, $iSize);

        // $icon->sampleImage(50, 50);
        // $icon->roundCorners(50, 50);

        // $cardBase->compositeImage($icon, Imagick::COMPOSITE_DEFAULT , 600, 10);


        $cardBase->writeImage(realpath("./") . '/app/img/card_base2.jpg');
    }


    // 公開リスト取得
    // フォロー関係取得
    //リツイート　statuses/show　のretweeted true/false
    // public function test(Request $request): JsonResponse {
    //     // dd($request->aaa . "  " . $request->bbb);
    //     $api_key = Consts::TWITTER_API_KEY;		// APIキー
    //     $api_secret = Consts::TWITTER_API_SECRET;		// APIシークレット

    //     $userData = Auth::User();
    //     $access_token = $userData->twitter_token;		// アクセストークン
    //     $access_token_secret = $userData->twitter_token_secret;		// アクセストークンシークレット   
    //     // dd($access_token . " " . $access_token_secret);

    //     $twObj = new TwitterOAuth($api_key,$api_secret,$access_token,$access_token_secret);
    //     // dd(Auth::User()->twitter_id);
    //     // $apiData = $twObj->get("lists/members/show", ["list_id" => "1565027332947329024", "screen_name" => "iphoneTaro_live"]);
    //     $apiData = $twObj->get("lists/members/show", ["list_id" => "1565027433249927168", "screen_name" => "iphoneTaro_live"]);

    //     if (property_exists($apiData, 'errors')) {
    //         dd("リストじゃない");
    //     } else {
    //         dd("リストだよ");
    //     }
    //     // $apiData = $twObj->get("users/show", ["screen_name" => "iphoneTaro_live"]);
    //     dd($apiData);

    //     // $statuses = $twObj->get("users/lookup", ["screen_name" => "iphoneTaro_live"]);
    //     // $jsonData = json_decode($statuses, true);
    //     // $list = array();
    //     // foreach ($apiData as $data) {
    //     //     array_push($list, ['id' => $data->id, 'name' => $data->name]);
    //     // }
    //     // dd($list);

    //     $vRequest = $twObj->OAuthRequest("http://api.twitter.com/1.1/friendships/lookup.xml","GET",array('screen_name' => 'kfukuda413,twitwi_info'));

    //     //XMLデータをsimplexml_load_string関数を使用してオブジェクトに変換する
    //     $oXml = simplexml_load_string($vRequest);

    //     //オブジェクトを展開
    //     if(isset($oXml->error) && $oXml->error != ''){
    //         echo "取得に失敗しました。<br/>\n";
    //         echo "パラメーターの指定を確認して下さい。<br/>\n";
    //         echo "エラーメッセージ:".$oXml->error."<br/>\n";
    //     }else{
    //         foreach($oXml as $oFriendships){
    //             echo "<p>自分と → <b>userid(".$oFriendships->id.") screen_name(".$oFriendships->screen_name.") username(".$oFriendships->name.")</b> との関係は、<br/>\n";
    //             foreach($oFriendships->connections->connection as $connection){
    //                 echo "-".$connection."<br/>\n";
    //             }
    //         }
    //     }

    //     return response()->json(['json' => $json, 'header' => $header]); 
    // }
}
