<?php declare(strict_types=1);

namespace App\Http\Controllers;

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

class DreamController extends Controller
{   
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
    //検索一覧取得
    //
    public function getPostList(Request $request): JsonResponse {
        //入力チェック
        try {
            $validatedData = $request->validate([
                'filter' => 'required|string|size:8',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //ユーザーIDを取得
		$userId = Auth::User()->user_id;
		$filterStr = $request->filter;
		$muteStr = Auth::User()->mute_tag;
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
		$fCreation0 = substr($filterStr, 6, 1);
		$fCreation1 = substr($filterStr, 7, 1);

        if (($fOrder != 0 && $fOrder != 1 && $fOrder != 2) ||
		($fDuration != 0 && $fDuration != 1 && $fDuration != 2 && $fDuration != 3) ||
		($fLen != 0 && $fLen != 1 && $fLen != 2 && $fLen != 3) ||
		($fRating0 != 0 && $fRating0 != 1) ||
		($fRating1 != 0 && $fRating1 != 1) ||
		($fRating2 != 0 && $fRating2 != 1) ||
		($fCreation0 != 0 && $fCreation0 != 1) ||
		($fCreation1 != 0 && $fCreation1 != 1)) {
            //フィルターチェック
			return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
		}
        
        //対象ページ
        if ($request->page != null) {
            $page = $request->page;
        } else {
            $page = 1;
        }
        $start = ($page - 1) * 10;
        
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
            `posts`.`publishing` != 99 AND 
            `mutes`.`mute_id` is null AND ";
        
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
            $fRatingStr = $fRatingStr . ") AND ";
            $sql = $sql . $fRatingStr;
        } else {
            $sql = $sql . "(`posts`.`rating` = 3) AND ";
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

        //検索ワード設定
		$wordSql = "";
		if ($wordStr != "") {
			//空白で分割
			$wordArray = explode(" ", $wordStr);

			for ($i = 0; $i < count($wordArray); $i++) {
                if ($wordArray[$i] != "") {
                    $wordSql = $wordSql . "(
                    `posts`.`title` LIKE '%" . $wordArray[$i] . "%' OR 
                    `posts`.`outline` LIKE '%" . $wordArray[$i] . "%' OR 
                    `posts`.`tags` LIKE '%" . $wordArray[$i] . "%' OR 
                    `users`.`user_name` LIKE '%" . $wordArray[$i] . "%' OR 
                    `posts`.`series` LIKE '%" . $wordArray[$i] . "%' ) AND ";
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

        $sql = $sql . " 1 = 1 ";

        //件数を先に取得
        $countSql = "SELECT count(*) as abc " . $sql;
        // dd($countSql);
        $postCount = \DB::select($countSql);

        //並べ替え
        if ($fOrder == 0) {
            $sql = $sql . "ORDER BY `posts`.`created_at` DESC ";
        } else if ($fOrder == 1) {
            $sql = $sql . "ORDER BY `posts`.`created_at` asc ";
        } else if ($fOrder == 2) {
            $sql = $sql . "ORDER BY `book` DESC, `posts`.`created_at` DESC ";
        }

        //件数
        $sql = $sql . "LIMIT " . $start . ", 10";
        $retArray = $retArray + array('sql' => $sql);

        //対象データ取得
        $dataSql = $sqlInit . $sql;
        $postList = \DB::select($dataSql);

        $retArray = $retArray + array('count' => $postCount[0]->abc);
        $retArray = $retArray + array('postList' => $postList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());
        $retArray = $retArray + array('test' => $request->test);
        
        return response()->json($retArray);
    }

    //
    //投稿一覧取得（自分）
    //
    public function getMyPostList(Request $request): JsonResponse {
        $postList = \DB::select('
            SELECT
             posts.post_id,
             posts.title,
             posts.outline,
             char_length(posts.body) AS length,
             posts.series,
             posts.rating,
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
                'title' => ['required'],
                'outline' => ['required'],
                'body' => ['required'],
                'series' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }
        
        $userId = Auth::User()->user_id;
        $title = $request->title;
        $outline = $request->outline;
        $body = $request->body;
        $conversion = $request->conversion;
        $series = $request->series;
        $rating = $request->rating;
        $creation = $request->creation;
        $tags = $request->tags;
        $filter = $request->filter;
        $publishing = $request->publishing;
        $searchable = $request->searchable;
        
        if ($searchable == true) {
            $searchable = 1;
        } else {
            $searchable = 0;
        }

        //異常値チェック
        if (($rating != 0 && $rating != 1 && $rating != 2) || 
        ($creation != 0 && $creation != 1) ||
        ($filter != 0 && $filter != 1 && $filter != 2 && $filter != 3) ||
        ($publishing != 0 && $publishing != 99) ||
        ($searchable != 0 && $searchable != 1)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => ""]);
        }

        //post_id生成
        $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $postId = "";
        while ($postId == "") {
            for ($i = 0; $i < 20; $i++) {
                $ch = substr($randomStr, mt_rand(0, strlen($randomStr)), 1);
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
        $postData->rating = $rating;
        $postData->creation = $creation;
        $postData->tags = $tags;
        $postData->filter = $filter;
        $postData->publishing = $publishing;
        $postData->searchable = $searchable;
        $postData->save();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $postId = $request->postId;
        $title = $request->title;
        $outline = $request->outline;
        $body = $request->body;
        $conversion = $request->conversion;
        $series = $request->series;
        $rating = $request->rating;
        $creation = $request->creation;
        $tags = $request->tags;
        $filter = $request->filter;
        $publishing = $request->publishing;
        $searchable = $request->searchable;

        //既存データ取得
        $postData = post::where('post_id', $postId)->first();

        if ($postData == null) {
            //該当なし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => $request->postId]);
        }

        //異常値チェック
        if (($rating != 0 && $rating != 1 && $rating != 2) || 
        ($creation != 0 && $creation != 1) ||
        ($filter != 0 && $filter != 1 && $filter != 2 && $filter != 3) ||
        ($publishing != 0 && $publishing != 99) ||
        ($searchable != 0 && $searchable != 1)) {
            //異常値エラー
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //情報を更新
        $postData->title = $title;
        $postData->outline = $outline;
        $postData->body = $body;
        $postData->conversion = $conversion;
        $postData->series = $series;
        $postData->rating = $rating;
        $postData->creation = $creation;
        $postData->tags = $tags;
        $postData->filter = $filter;
        $postData->publishing = $publishing;
        $postData->searchable = $searchable;
        $postData->save();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $userId = Auth::User()->user_id;
        $targetId = $request->targetId;
        $muteStr = Auth::User()->mute_tag;

        $retArray = array();
        
        //対象のデータ取得
        $targetUserData = user::where('user_id', $targetId)->first();

        if ($targetUserData == null) {
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'msg' => ""]);
        }
        $retArray = $retArray + array('user_name' => $targetUserData->user_name);
        $retArray = $retArray + array('user_profile' => $targetUserData->profile);



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

        $retArray = $retArray + array('postList' => $postList);
        $retArray = $retArray + array('status' => Consts::API_SUCCESS);
        $retArray = $retArray + array('baseInfo' => $this->retUserInfo());

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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
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
    //設定 － 設定情報取得（ミュートの一覧取得だけ？）
    //
    public function getSettingInfo(Request $request): JsonResponse {
        $muteList = mute::LEFTJOIN('users', 'mutes.mute_id', '=', 'users.user_id')->where('mutes.user_id', Auth::User()->user_id)
        ->get(['mutes.mute_id', 'users.user_name']);

        return response()->json(['status' => Consts::API_SUCCESS, 'muteList' => $muteList, 'baseInfo' => $this->retUserInfo()]);
    }

    //
    //設定 － 基本情報更新
    //
    public function updateSettingBase(Request $request): JsonResponse {
        $file = $request->file('iconFile');

        //入力チェック
        try {
            $validateArray = [
                'userName' => ['required'],
            ];
    
            if ($file != null) {
                //アイコン添付あり
                $validateArray += array('iconFile' => 'max:1024|mimes:jpg,jpeg,png');
            }

            $validatedData = $request->validate($validateArray);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $userName = $request->userName;
        $profile = $request->profile;
        // $file = $request->file('iconFile']['tmp_name'];
        $file = $request->file('iconFile');
        $fileName = $request->iconFileName;

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->user_name = $userName;
        $userData->profile = $profile;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->save();

        //アイコンファイルがある場合は更新
        if ($file != null) {
            //チェック？？？？
            $file->storeAs('public/icon', $userData->user_id . ".png");
        } else if ($fileName == "default") {
            copy('./app/img/icon/icon_default.png', '../storage/app/public/icon/' . $userData->user_id . '.png');
        }
        
        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $userData]);
    }

    //
    //設定 － お気に入りタグ更新
    //
    public function updateSettingFavorite(Request $request): JsonResponse {
        $favorite = $request->favorite;

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

        //ユーザー情報を取得
        $userData = Auth::User();

        //情報を更新
        $userData->mute_tag = $mute;
        // $userData->updated_at = date("Y/m/d H:i:s");
        $userData->update();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
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
            posts.creation,
            posts.tags,
            posts.filter,
            posts.publishing,
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

        if ($postData->publishing == "99") {
            //非公開の場合
            return response()->json(['status' => Consts::API_FAILED_PRIVATE, 'errMsg' => '非公開データ']);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
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
                'stamp' => ['required'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        DB::table('stamps')->insert([
            'post_id' => $request->postId,
            'stamp_id' => $request->stamp
        ]);

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
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
            posts.creation,
            posts.tags,
            posts.filter,
            posts.publishing,
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

        return response()->json(['status' => Consts::API_SUCCESS, 'postData' => $postData, 'seriesList' => $seriesList, 'baseInfo' => $this->retUserInfo()]);
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
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $postId = $request->postId;

        //削除
        $data = Post::where('post_id', $postId)->delete();

        return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
    }

    public function test() {
        $title = '太郎テスト';
        $body = 'ほんぶんほんぶんほんぶんほんぶんほんぶんほんぶんほんぶんほんぶんほんぶんほんぶん';
        $email = 'saga.siga.noga@gmail.com';

        Mail::send(new MailMgr($title, $body, $email));

        dd("OK");
    }
}
