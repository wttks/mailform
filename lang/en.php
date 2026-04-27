<?php

/**
 * 英語の翻訳マップ。
 * キーは Validate{Xxx} の getErrorMessage() で定義された日本語デフォルト文（完全一致）。
 * :title / :min / :max / :type / :format / :field 等のプレースホルダはそのまま残す。
 */
return [
    // 必須・基本型
    ':titleは必須項目です。'                                       => ':title is required.',
    ':fieldを設定している場合:titleは必須です。'                   => ':title is required when :field is set.',
    ':titleは文字列を指定してください。'                           => ':title must be a string.',
    ':titleは整数で入力してください。'                             => ':title must be an integer.',
    ':titleは配列で指定してください。'                             => ':title must be an array.',

    // 形式
    ':titleはメールアドレスの形式で入力してください。'             => ':title must be a valid email address.',
    ':titleはメールアドレスの一覧を入力してください。'             => ':title must be a list of valid email addresses.',
    ':titleは正しいURLの形式で入力してください。'                  => ':title must be a valid URL.',
    ':titleは電話番号の形式で入力してください。'                   => ':title must be a valid telephone number.',
    ':titleには郵便番号を入力してください'                          => ':title must be a valid postal code.',
    ':titleは日付の形式で入力してください。'                       => ':title must be a valid date.',
    ':titleは日時の形式で入力してください。'                       => ':title must be a valid datetime.',
    ':titleは日付の範囲指定形式で入力してください。'               => ':title must be a valid date range.',
    ':titleは:formatの形式で入力してください。'                    => ':title must match the format :format.',
    ':titleはHH:MMの形式で入力してください。'                      => ':title must be in HH:MM format.',
    ':titleは半角英数字で入力してください。'                       => ':title must be alphanumeric.',
    ':titleは半角英字で入力してください。'                         => ':title must contain only alphabetic characters.',
    ':titleは:typeで入力してください。'                            => ':title must be entered as :type.',
    ':titleは日本語を含めて入力してください。'                     => ':title must contain Japanese characters.',

    // 比較・選択
    ':titleが:fieldと一致しません。'                               => ':title must match :field.',
    ':titleは指定された値の中から選択してください。'               => ':title must be one of the allowed values.',

    // ファイル
    ':titleはファイルをアップロードしてください。'                 => 'Please upload a file for :title.',
    ':titleは画像ファイルを指定してください。'                     => ':title must be an image file.',
    ':titleは:typesのファイルを指定してください。'                 => ':title must be a file of type: :types.',

    // サイズ系（Min）
    ':titleは:min以上を指定してください。'                         => ':title must be at least :min.',
    ':titleは:min文字以上で入力してください。'                     => ':title must be at least :min characters.',
    ':titleは:minより後の日付を指定してください。'                 => ':title must be a date after :min.',
    ':titleは:minより後の日時を指定してください。'                 => ':title must be a datetime after :min.',
    ':titleは:min個以上で入力してください。'                       => ':title must contain at least :min items.',
    ':titleは:min以上のサイズのファイルを指定してください。'       => ':title must be a file at least :min in size.',

    // サイズ系（Max）
    ':titleは:max以下の数値を指定してください。'                   => ':title must be no greater than :max.',
    ':titleは:max文字以下で入力してください。'                     => ':title must be no longer than :max characters.',
    ':titleは:maxより前の日付を指定してください。'                 => ':title must be a date before :max.',
    ':titleは:maxより前の日時を指定してください。'                 => ':title must be a datetime before :max.',
    ':titleは:max個以下で入力してください。'                       => ':title must contain no more than :max items.',
    ':titleは:max以下のサイズのファイルを指定してください。'       => ':title must be a file no greater than :max in size.',

    // サイズ系（Between）
    ':titleは:min以上:max以下の数値を指定してください。'           => ':title must be between :min and :max.',
    ':titleは:min文字以上:max文字以下で入力してください。'         => ':title must be between :min and :max characters.',
    ':titleは:minより後:maxより前の日付を指定してください。'       => ':title must be a date between :min and :max.',
    ':titleは:minより後:maxより前の日時を指定してください。'       => ':title must be a datetime between :min and :max.',
    ':titleは:min個以上:max個以下で入力してください。'             => ':title must contain between :min and :max items.',
    ':titleは:min以上:max以下のサイズのファイルを指定してください。' => ':title must be a file between :min and :max in size.',
];
