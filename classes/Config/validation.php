<?php
return [
    'file'      => [
        'title'    => '添付ファイル',
        'validate' => 'required|file',
    ],
    'email'     => [
        'title'    => 'メールアドレス',
        'validate' => 'required|email',
    ],
    'full_name' => [
        'title'    => 'お名前',
        'validate' => 'required',
        'output'   => false,
    ],
];


