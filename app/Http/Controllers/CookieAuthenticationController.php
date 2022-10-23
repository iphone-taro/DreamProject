<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Temp;

use App\Http\Controllers\DreamController;

use App\Consts\Consts;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailMgr;

//100   未認証
//200   成功
//300   入力エラー
//400   DBエラー


final class CookieAuthenticationController extends Controller
{
    public function tete (Request $request) {
        $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $tempId = "";

        $array = array();
        for ($j=0; $j < 100; $j++) {
            $tempId = "";
            $curLen = 0;
            for ($i = 0; $i < 20; $i++) {
                $ran = mt_rand(0, strlen($randomStr) - 1);
                $ch = substr($randomStr, $ran, 1);
                $tempId = $tempId . $ch;
                $len = strlen($tempId);
                if ($curLen == $len) {
                    dd($ch . "" . $ran);
                }
                $curLen = $len;
            }

            $str = strlen($tempId) . "-" . $tempId;
            array_push($array, $str);
        }
        dd($array);
    }

    //
    //メールアドレスチェック
    //
    public function checkAddress($mailAddress) {
        if (strpos($mailAddress, '@') == false) {
            //含まれているか
            return false;
        } else {
            $array = explode('@', $mailAddress);

            if (count($array) != 2) {
                //@複数
                return false;
            } else if (strpos($array[0], '.jp') == false && strpos($array[0], '.com') == false && 
                strpos($array[0], '.net') == false && strpos($array[0], '.org') == false && strpos($array[0], '.xyz') == false) {
                //指定以外
                return false;
            }
        }
        return true;
    }

    //
    //パスワードチェック
    //
    public function checkPassword($password) {
        $pattern = "/^[a-z0-9#!?&$%+-]{0,32}$/i";
        if (preg_match($pattern, $password)) {
            dd("TRUE");
            return true;
        } else {
            dd("FALSE");
            return false;
        }
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

    public function getHash() {
        $param = $_GET["param"];
        
        return Hash::make($param);
    }

    //
    //認証状態取得
    //
    public function isAuth(): JsonResponse {
        $retArray = array();

        if (Auth::check()) {
            //ログイン済み
            $retArray = $retArray + array("baseInfo" => Auth::User());
        } else {
            //未ログイン
            $retArray = $retArray + array("baseInfo" => null);
        }
        $retArray = $retArray + array("status" => Consts::API_SUCCESS);

        return response()->json($retArray); 
    }

    //
    //パスワードリセット申請処理（メール、パスワード）
    //
    public function requestReset(Request $request): JsonResponse {

        //バリデート
        try {
            $validated = $request->validate([
                'mailAddress' => 'required|max:200|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //メールアドレスチェック
        if ($this->checkAddress($request->mailAddress)) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => "MailAddress"]);
        }

        //メールアドレスから存在チェック
        $chkExist = DB::table('users')->where('mail_address', $request->mailAddress)->first();

        if ($chkExist == null) {
            //対象アカウントなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'アカウントなし' . $request->mailAddress]);
        }

        //一時テーブルに挿入
        $tempData = Temp::where('mail_address', $request->mailAddress)->where('temp_kbn', 'RESET_PASS')->whereNull('password')->first();

        $mailTitle = 'パスワードリセット';
        $mailBody = 'パスワードリセット用のURLです<BR>http://localhost:8001/#/resetPassword/';
        $mailEmail = $request->mailAddress;    

        if ($tempData == null) {
            //データがない場合は新規挿入
            $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $tempId = "";

            while ($tempId == "") {
				for ($i = 0; $i < 20; $i++) {
					$ch = substr($randomStr, mt_rand(0, strlen($randomStr)) - 1, 1);
					$tempId = $tempId . $ch;
				}

				//重複チェック
                $checkData = DB::table('temps')->where('temp_id', $tempId)->first();

				if ($checkData != null) {
					$tempId = "";
				}
			}

            $tempData = new temp;
            $tempData->temp_id = $tempId;
            $tempData->temp_kbn = "RESET_PASS";
            $tempData->mail_address = $request->mailAddress;
            $tempData->limit_date = date("Y/m/d H:i:s", strtotime("1 day"));

            try {
                DB::beginTransaction();
                
                $flg = $tempData->save();

                if ($flg) {

                    //メールを送信
                    $mailBody = $mailBody . $tempData->temp_id;
                    Mail::send(new MailMgr($mailTitle, $mailBody, $mailEmail));

                    DB::commit();
                    return response()->json(['status' => Consts::API_SUCCESS, 'tempId' => $tempId]); 
                } else {
                    return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'リセット申請エラー']);
                }
            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'リセット申請エラー']);
            }
        } else {
            //すでにある場合は期限を更新
            $tempData->limit_date = date("Y/m/d H:i:s", strtotime("1 day"));

            try {
                DB::beginTransaction();
    
                $flg = $tempData->save();

                if ($flg) {
                    //メールを送信
                    $mailBody = $mailBody . $tempData->temp_id;
                    Mail::send(new MailMgr($mailTitle, $mailBody, $mailEmail));

                    DB::commit();
                    return response()->json(['status' => Consts::API_SUCCESS, 'tempId' => $tempData->temp_id]); 
                } else {
                    return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'リセット申請エラー']);
                }

            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);
            }
        }
    }

    //
    //パスワードリセット初期処理
    //
    public function resetPasswordInit(Request $request): JsonResponse {
        //バリデート
        try {
            $validated = $request->validate([
                'tempId' => 'required|string|size:20',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //対象のデータをtempから取得
        $tempData = Temp::where('temp_id', $request->tempId)->where('temp_kbn', 'RESET_PASS')->whereNull('password')->where('limit_date', '>=', date("Y/m/d H:i:s"))->first();

        if ($tempData == null) {
            //対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'TEMP']);
        }

        //userからデータ取得
        $userData = User::where('mail_address', $tempData->mail_address)->first();

        if ($userData == null) {
            //メールアドレスの対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'USER']);
        }

        return response()->json(['status' => Consts::API_SUCCESS]);
    }

    //
    //パスワードリセット処理（メール、パスワード）
    //
    public function resetPassword(Request $request): JsonResponse {

        //バリデート
        try {
            $validated = $request->validate([
                'tempId' => 'required|string|size:20',
                'newPassword' => 'required|min:8|max:32',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }
        
        //パスワードの正規表現チェック
        if (preg_match(Consts::REGEX_PASSWORD, $request->newPassword) == 0) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }
        
        //対象のデータをtempから取得
        $tempData = Temp::where('temp_id', $request->tempId)->where('temp_kbn', 'RESET_PASS')->whereNull('password')->where('limit_date', '>=', date("Y/m/d H:i:s"))->first();

        if ($tempData == null) {
            //対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'TEMP']);
        }

        //userからデータ取得
        $userData = User::where('mail_address', $tempData->mail_address)->first();
        
        if ($userData == null) {
            //メールアドレスの対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'USER']);
        }

        //ユーザーテーブルを更新
        $userData->password = Hash::make($request->newPassword);
        $flg = $userData->save();

        if ($flg) {
            //tempテーブルからデータを削除
            $deleteTemp = $tempData->delete();
            
            return response()->json(['status' => Consts::API_SUCCESS]);
        } else {
            return response()->json(['status' => Consts::API_EXCEPTION, 'errMsg' => 'リセットエラー']);
        }
    }

    //
    //仮登録処理（メール、パスワード）
    //
    public function requestRegister(Request $request): JsonResponse {
        //バリデート
        try {
            $validated = $request->validate([
                'mailAddress' => 'required|max:200|string',
                'kbn' => 'required',
            ]);

            $kbn = $request->kbn;

            //kbnチェック
            if ($kbn != 'NEW' && $kbn != 'ADD' && $kbn != 'CHANGE') {
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => ""]);
            }

            if ($kbn == "NEW" || $kbn == "ADD") {
                //追加と変更の場合、パスワードチェック
                $validated = $request->validate([
                    'password' => 'required|min:8|max:32',
                ]);

                //パスワードの正規表現チェック
                if (preg_match(Consts::REGEX_PASSWORD, $request->password) == 0) {
                    return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
                }
            }
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //メールアドレスチェック
        if ($this->checkAddress($request->mailAddress)) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => "MailAddress"]);
        }
        
        //メールアドレスから存在チェック
        $chkExist = DB::table('users')->where('mail_address', $request->mailAddress)->first();

        if ($chkExist != null) {
            //メールアドレス重複
            return response()->json(['status' => Consts::API_FAILED_DUPLICATE, 'errMsg' => 'メールアドレス重複'. $request->mailAddress]);
        }

        //一時テーブルに挿入
        $tempData = Temp::where('mail_address', $request->mailAddress)->where('temp_kbn', $kbn)->first();            
       
        $mailTitle = '新規登録用メール';
        $mailBody = '新規登録用のURLです<BR>http://localhost:8001/#/registrationConfirmation/';
        $mailEmail = $request->mailAddress; 

        //メール送信処理
        if ($tempData == null) {
            //データがない場合は新規挿入
            $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $tempId = "";

            while ($tempId == "") {
				for ($i = 0; $i < 20; $i++) {
					$ch = substr($randomStr, mt_rand(0, strlen($randomStr)) - 1, 1);
					$tempId = $tempId . $ch;
				}

				//重複チェック
                $checkData = DB::table('temps')->where('temp_id', $tempId)->first();

				if ($checkData != null) {
					$tempId = "";
				}
			}
            
            $tempData = new temp;
            $tempData->temp_id = $tempId;
            $tempData->temp_kbn = $kbn;

            if ($kbn == "ADD" || $kbn == "CHANGE") {
                //追加と変更の場合
                $userData = Auth::User();
                $tempData->user_id = $userData->user_id;
            } else {
                //新規の場合
                $tempData->user_id = "";
            }

            //メールアドレス
            $tempData->mail_address = $request->mailAddress;

            if ($kbn == "CHANGE") {
                //変更の場合
                $tempData->password = null;
            } else {
                //新規 追加の場合
                $tempData->password = Hash::make($request->password);
            }

            //期限を設定
            $tempData->limit_date = date("Y/m/d H:i:s", strtotime("1 day"));

            try {
                DB::beginTransaction();

                //更新
                $tempData->save();

                //メールを送信
                $mailBody = $mailBody . $tempData->temp_id;
                Mail::send(new MailMgr($mailTitle, $mailBody, $mailEmail));

                DB::commit();

                if ($kbn == "ADD" || $kbn == "CHANGE") {
                    //追加と変更の場合
                    return response()->json(['status' => Consts::API_SUCCESS, 'temp_id' => $tempData->temp_id, 'baseInfo' => $this->retUserInfo()]);
                } else if ($kbn == "NEW") {
                    //新規の場合
                    return response()->json(['status' => Consts::API_SUCCESS, 'temp_id' => $tempData->temp_id]); 
                }
            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);
            }
        } else {
            //すでにある場合はパスワードと数字と期限を更新
            if ($kbn != "CHANGE") {
                //新規 追加の場合
                $tempData->password = Hash::make($request->password);
            } else {
                //変更の場合
                $tempData->password = null;
            }

            if ($kbn == "ADD" || $kbn == "CHANGE") {
                //追加と変更の場合
                $userData = Auth::User();
                $tempData->user_id = $userData->user_id;
            } else {
                //新規の場合
                $tempData->user_id = "";
            }

            $tempData->limit_date = date("Y/m/d H:i:s", strtotime("1 day"));

            try {
                DB::beginTransaction();

                $tempData->save();
                
                //メールを送信
                $mailBody = $mailBody . $tempData->temp_id;
                Mail::send(new MailMgr($mailTitle, $mailBody, $mailEmail));

                DB::commit();

                if ($kbn == "ADD" || $kbn == "CHANGE") {
                    return response()->json(['status' => Consts::API_SUCCESS, 'temp_id' => $tempData->temp_id, 'baseInfo' => $this->retUserInfo()]);
                } else if ($kbn == "NEW") {
                    return response()->json(['status' => Consts::API_SUCCESS, 'temp_id' => $tempData->temp_id]); 
                }
            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);
            }
        }
    }

    //
    //本登録処理（メール、パスワード）
    //
    public function register(Request $request): JsonResponse {

        //バリデート
        try {
            $validated = $request->validate([
                'tempId' => 'required|string|size:20',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }
        
        //対象のデータをtempから取得
        $tempData = Temp::where('temp_id', $request->tempId)
            ->where(function($query) {
                $query->where('temp_kbn', '=', 'ADD')->whereNotNull('password');})
                ->orWhere(function($query) {
                    $query->where('temp_kbn', '=', 'NEW')->whereNotNull('password');})
                ->orWhere(function($query) {
                    $query->where('temp_kbn', '=', 'CHANGE')->whereNull('password');})
                ->where('limit_date', '>=', date("Y/m/d H:i:s"))->first();

        if ($tempData == null) {
            //メールアドレスの対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'TEMP']);
        }

        //メールアドレスから存在チェック
        $chkExist = DB::table('users')->where('mail_address', $tempData->mail_address)->first();
        if ($chkExist != null) {
            //メールアドレス重複
            return response()->json(['status' => Consts::API_FAILED_DUPLICATE, 'errMsg' => 'メールアドレス重複'. $tempData->mail_address]);
        }

        $kbn = $tempData->temp_kbn;

        if ($kbn == 'NEW') {
            //新規登録用
            //ユーザーテーブルへ挿入
            $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $userId = "";

            while ($userId == "") {
                for ($i = 0; $i < 20; $i++) {
                    $ch = substr($randomStr, mt_rand(0, strlen($randomStr)) - 1, 1);
                    $userId = $userId . $ch;
                }

                //重複チェック
                $checkData = DB::table('users')->where('user_id', $userId)->first();

                if ($checkData != null) {
                    $userId = "";
                }
            }

            $newUser = new user; 
            $newUser->user_id = $userId;
            $newUser->user_name = 'ドロップユーザー';
            $newUser->profile = 'はじめまして';
            $newUser->twitter_id = '';
            $newUser->mail_address = $tempData->mail_address;
            $newUser->password = $tempData->password;

            try {
                DB::beginTransaction();
                

                $flg = $newUser->save();

                if ($flg) {
                    //アイコンファイルを作成
                    copy('./app/img/icon/icon_default.png', '../storage/app/public/icon/' . $userId . '.png');

                    //tempテーブルからデータを削除
                    $deleteTemp = $tempData->delete();

                    DB::commit();                 
                    return response()->json(['status' => Consts::API_SUCCESS, 'kbn' => $kbn]);
                } else {
                    DB::rollBack();
                    return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '本登録エラー']);
                }
            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);
            }
        } else if ($kbn == "CHANGE" || $kbn == "ADD") {
            //対象のユーザーを取得
            $userData = User::where('user_id', $tempData->user_id)->first();

            //追加用
            $userData->mail_address = $tempData->mail_address;
            if ($kbn == "ADD") {
                $userData->password = $tempData->password;
            }
            $flg = $userData->save();

            //tempテーブルからデータを削除
            $deleteTemp = $tempData->delete();

            //ログアウト処理
            Auth::logout();

            if ($flg) {
                return response()->json(['status' => Consts::API_SUCCESS, 'kbn' => $kbn]);
            } else {
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'メール追加エラー']);
            }
        }
    }

    //
    //パスワード更新
    //
    public function updateAuthPassword(Request $request): JsonResponse {
        //バリデート
        try {
            $validated = $request->validate([
                'currentPassword' => 'required|min:8|max:32',
                'newPassword' => 'required|min:8|max:32',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $userData = Auth::User();

        //パスワードの正規表現チェック
        if (preg_match(Consts::REGEX_PASSWORD, $request->currentPassword) == 0) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //パスワードの正規表現チェック
        if (preg_match(Consts::REGEX_PASSWORD, $request->newPassword) == 0) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        // $chkData = user::where('user_id', $userData->user_id)->where('password', password_hash($request->currentPassword, PASSWORD_DEFAULT))->first();
        // $chkData = user::where('user_id', $userData->user_id)->where('password', $request->currentPassword)->first();

        if (Hash::check($request->currentPassword, $userData->password) == false) {
            //パスワード不一致
            return response()->json(['status' => Consts::API_FAILED_MISMATCH, 'errMsg' => '既存パスワードエラー' . $userData->password . " " . password_hash($request->currentPassword, PASSWORD_DEFAULT)]);
        }

        //パスワード更新
        $userData->password = Hash::make($request->newPassword);
        $flg = $userData->save();

        if ($flg) {
            return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
        } else {
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'パスワード更新エラー']);
        }
    }

    //
    //Twitter認証追加（設定から）
    //
    public function addAuthTwitter(Request $request): JsonResponse {
        //バリデート
        try {
            $validated = $request->validate([
                'twitterId' => 'required'
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //排他チェック
        $chkData = user::where('twitter_id', $request->twitterId)->first();
        if ($chkData != null) {
            return response()->json(['status' => Consts::API_FAILED_DUPLICATE, 'msg' => $e->getMessage()]);
        }

        //ユーザーデータ取得
        $userData = Auth::User();
        $userData->twitter_id = $request->twitterId;
        $flg = $userData->save();

        if ($flg) {
            return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
        } else {
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'Twitter追加エラー']);
        }
    }

    //
    //メール認証削除（設定から）
    //
    public function deleteAuthMail(Request $request): JsonResponse {
        $userData = Auth::User();

        if ($userData->twitter_id == "") {
            //エラー
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'メール削除エラー']);
        }

        $userData->mail_address = "";
        $userData->password = "";
        $flg = $userData->save();

        if ($flg) {
            return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
        } else {
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'メール削除エラー']);
        }
    }
    
    //
    //Twitter認証削除（設定から）
    //
    public function deleteAuthTwitter(Request $request): JsonResponse {
        $userData = Auth::User();

        if ($userData->mail_address == "") {
            //エラー
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => 'Twitter削除エラー']);
        }
        
        $userData->twitter_id = "";
        $flg = $userData->save();

        if ($flg) {
            return response()->json(['status' => Consts::API_SUCCESS, 'baseInfo' => $this->retUserInfo()]);
        } else {
            return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => 'Twitter削除エラー']);
        }
    }
    
    //
    //ログイン処理（メール、パスワード）
    //
    public function loginMail(Request $request): JsonResponse
    {
        //入力チェック
        try {
            $credentials = $request->validate([
                'mailAddress' => 'required|max:200|string',
                'password' => 'required|min:8|max:32',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }
        
        //メールアドレスチェック
        if ($this->checkAddress($request->mailAddress)) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => "MailAddress"]);
        }

        //パスワードの正規表現チェック
        if (preg_match(Consts::REGEX_PASSWORD, $request->password) == 0) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        $mess = $request->mailAddress . " " . $request->password;

        if (Auth::attempt(['mail_address' => $request->mailAddress, 'password' => $request->password])) {
            $user = Auth::User();
            return response()->json(['status' => Consts::API_SUCCESS]);
        }

        //ログイン失敗
        return new JsonResponse(['status' => Consts::API_FAILED_LOGIN, 'message' => $mess]);
    }

    //
    //ログイン処理（Twitter）
    //
    public function loginTwitter(Request $request): JsonResponse
    {
        //入力チェック
        try {
            $credentials = $request->validate([
                'twitterId' => 'required',
                'twitterCode' => 'required',
                'twitterToken' => 'required',
                'twitterTokenSecret' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //ユーザーテーブルから該当を取得
        $userData = user::where('twitter_id', $request->twitterId)->first();

        if ($userData == null) {
            //対象データなし
            return response()->json(['status' => Consts::API_FAILED_NODATA, 'errMsg' => ""]);
        } else {
            //twitterCodeを更新
            $userData->twitter_code = $request->twitterCode;
            $userData->twitter_token = $request->twitterToken;
            $userData->twitter_token_secret = $request->twitterTokenSecret;
            
            $flg = $userData->save();

            if (!$flg) {
                //更新エラー
                return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => 'twitterCode更新エラー']);
            }

            //データがある場合、ログイン成功
            try {
                Auth::login($userData);                
            } catch (AuthenticationException $e) {
                return response()->json(['status' => Consts::API_FAILED_LOGIN]);
            }
            return response()->json(['status' => Consts::API_SUCCESS]);
        }
    }

    //
    //登録処理（Twitter）
    //
    public function registerTwitter(Request $request): JsonResponse
    {
        //入力チェック
        try {
            $credentials = $request->validate([
                'twitterId' => 'required',
                'twitterCode' => 'required',
                'twitterToken' => 'required',
                'twitterTokenSecret' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => Consts::API_FAILED_PARAM, 'msg' => $e->getMessage()]);
        }

        //ユーザーテーブルから該当を取得
        $userData = user::where('twitter_id', $request->twitterId)->first();
        
        if ($userData != null) {
            //登録済みの場合
            return response()->json(['status' => Consts::API_FAILED_DUPLICATE, 'errMsg' => ""]);
        } else {
            //新規作成
            //ユーザーID生成
            $randomStr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $userId = "";

            while ($userId == "") {
				for ($i = 0; $i < 20; $i++) {
					$ch = substr($randomStr, mt_rand(0, strlen($randomStr)) - 1, 1);
					$userId = $userId . $ch;
				}

				//重複チェック
                $checkData = DB::table('users')->where('user_id', $userId)->first();

				if ($checkData != null) {
					$userId = "";
				}
			}

            $twitterId = $request->twitterId;
            $userName = $request->twitterName;
            $iconUrl = $request->twitterIconUrl;
            $twitterCode = $request->twitterCode;
            $twitterToken = $request->twitterToken;
            $twitterTokenSecret = $request->twitterTokenSecret;
            
            //データ挿入
            $newUser = new user;
            $newUser->user_id = $userId;
            $newUser->user_name = $userName;
            $newUser->profile = "はじめまして";
            $newUser->twitter_id = $twitterId;
            $newUser->twitter_code = $twitterCode;
            $newUser->twitter_token = $twitterToken;
            $newUser->twitter_token_secret = $twitterTokenSecret;
            $newUser->mail_address = '';
            $newUser->password = '';
            $newUser->email_verified_at = date("Y/m/d H:i:s");

            try {
                DB::beginTransaction();
                
                $flg = $newUser->save();
                
                if (!$flg) {
                    return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);
                }
                
                //アイコンファイルの生成
                $newFileName = '../storage/app/public/icon/' . $userId . ".png";
                $image_path = file_get_contents($iconUrl);
                file_put_contents($newFileName, $image_path);
                
                Auth::login($newUser);                
              
                DB::commit();
                return response()->json(['status' => Consts::API_SUCCESS]);
                
            } catch (AuthenticationException $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_LOGIN]);
            } catch (Throwable $e) {
                DB::rollBack();
                return response()->json(['status' => Consts::API_FAILED_EXEPTION, 'errMsg' => '仮登録エラー']);                
            }
        }
    }

    //
    //ログアウト
    //
    public function logout()
    {
        Auth::logout();
        return response()->json(['status' => Consts::API_SUCCESS]);
    }
}