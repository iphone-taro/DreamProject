<?php

namespace App\Consts;

class Consts
{
    //メールアカウント新規登録申請
    public const MAIL_TITLE_MAIL_NEW_REQ = "【yumedrop】仮登録完了のお知らせ";
    public const MAIL_BODY_MAIL_NEW_REQ = "以下に記載されたアドレス（URL）にアクセスして、メールアドレスの認証を行ってください。\nこのURLは送信より24時間まで有効です。\n\nhttps://yumedrop.com/registrationConfirmation/###\n\n本メールに心当たりのない場合は、\nお手数ですが本メールを破棄いただきますようお願いいたします。\n上記URLにアクセスをしなければ、\n本登録完了とはなりませんので、退会手続きは必要ございません。\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";
    //メールアカウント新規登録完了
    public const MAIL_TITLE_MAIL_NEW_COMP = "【yumedrop】本登録完了のお知らせ";
    public const MAIL_BODY_MAIL_NEW_COMP = "本登録が完了いたしました。\n以下のアドレス（URL）より、ご利用いただけます。\n\nhttps://yumedrop.com\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";

    //パスワードリセット申請
    public const MAIL_TITLE_PASS_REQ = "【yumedrop】パスワードの再設定";
    public const MAIL_BODY_PASS_REQ = "パスワードを再設定します。\n\n以下に記載されたアドレス（URL）にアクセスして、新たなパスワードを入力してください。\nこのURLは送信より24時間まで有効です。\n\nhttps://yumedrop.com/resetPassword/###\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";
    //パスワードリセット完了
    public const MAIL_TITLE_PASS_COMP = "【yumedrop】パスワードの再設定完了のお知らせ";
    public const MAIL_BODY_PASS_COMP = "パスワードの再設定が完了しました。\n引き続き、以下のアドレス（URL）より、ご利用いただけます。\n\nhttps://yumedrop.com\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";

    //メールアカウント追加申請
    public const MAIL_TITLE_MAIL_ADD_REQ = "【yumedrop】メールアドレス仮登録完了のお知らせ";
    public const MAIL_BODY_MAIL_ADD_REQ = "以下に記載されたアドレス（URL）にアクセスして、メールアドレスの認証を行ってください。\nこのURLは送信より24時間まで有効です。\n\nhttps://yumedrop.com/registrationConfirmation/###\n\n本メールに心当たりのない場合は、\nお手数ですが本メールを破棄いただきますようお願いいたします。\n上記URLにアクセスをしなければ、\n本登録完了とはなりませんので、退会手続きは必要ございません。\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";
    //メールアカウント追加完了
    public const MAIL_TITLE_MAIL_ADD_COMP = "【yumedrop】メールアドレス本登録完了のお知らせ";
    public const MAIL_BODY_MAIL_ADD_COMP = "メールアドレスの本登録が完了いたしました。\n以下のアドレス（URL）より、ご利用いただけます。\n\nhttps://yumedrop.com\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";

    //メールアドレス変更申請
    public const MAIL_TITLE_MAIL_CHANGE_REQ = "【yumedrop】メールアドレスの変更";
    public const MAIL_BODY_MAIL_CHANGE_REQ = "メールアドレスを変更します。\n\n以下に記載されたアドレス（URL）にアクセスして、メールアドレスの認証を行ってください。\nこのURLは送信より24時間まで有効です。\n\nhttps://yumedrop.com/registrationConfirmation/###\n\n本メールに心当たりのない場合は、\nお手数ですが本メールを破棄いただきますようお願いいたします。\n上記URLにアクセスをしなければ、\n認証完了とはなりませんので、このメールアドレスでの退会手続きは必要ございません。\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";
    //メールアドレス変更完了
    public const MAIL_TITLE_MAIL_CHANGE_COMP = "【yumedrop】メールアドレス変更完了のお知らせ";
    public const MAIL_BODY_MAIL_CHANGE_COMP = "メールアドレスの変更が完了しました。\n引き続き、以下のアドレス（URL）より、ご利用いただけます。\n\nhttps://yumedrop.com\n\nこのメールは送信専用です。\nご返信いただいてもお返事できかねますので、あらかじめご了承ください。\n\n=============================\nyumedrop\nhttps://yumedrop.com\n\n▼お問い合わせは\nURL\n\n▼利用規約\nURL\n\n▼プライバシーポリシー\nURL\n=============================    ";
    

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
