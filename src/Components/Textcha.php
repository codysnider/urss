<?php

namespace RssApp\Components;

class Textcha
{
    const LOCAL_QUESTIONS = [
        [
            'q' => 'Of the numbers seventy one, 4, fifty seven, 70, 49 or forty four, which is the highest?',
            'a' => [
                'e2c420d928d4bf8ce0ff2ec19b371514',
                '8456ebf0e76ac3f80fc5adc4dfb46e42'
            ]
        ],
        [
            'q' => 'In the number 4519096, what is the 4th digit?',
            'a' => [
                '45c48cce2e2d7fbdea1afc51c7c6ad26',
                'c785e1ed2950e3e36b1e2ca01f299a54'
            ]
        ],
        [
            'q' => 'Steven\'s name is?',
            'a' => [
                '6ed61d4b80bb0f81937b32418e98adca'
            ]
        ],
        [
            'q' => 'Which digit is 5th in the number 2993975?',
            'a' => [
                '45c48cce2e2d7fbdea1afc51c7c6ad26',
                'c785e1ed2950e3e36b1e2ca01f299a54'
            ]
        ],
        [
            'q' => 'Which is the highest number? Ninety four, 56 or seventy seven?',
            'a' => [
                'f4b9ec30ad9f68f89b29639786cb62ef',
                '96b0d8d3390fa62eb2f26db0bca9b2ef'
            ]
        ],
        [
            'q' => 'In the number 222457, what is the 6th digit?',
            'a' => [
                '8f14e45fceea167a5a36dedd4bea2543',
                'bb3aec0fdcdbc2974890f805c585d432'
            ]
        ]
    ];

    public static function getQuestion(): array
    {
        if (getenv('CAPTCHA_ALLOW_THIRD_PARTY') === 'true') {
            $question = json_decode(file_get_contents('http://api.textcaptcha.com/rssapp.json'), true);
        } else {
            $question = self::LOCAL_QUESTIONS[array_rand(self::LOCAL_QUESTIONS)];
        }

        foreach ($question['a'] as $index => $answer) {
            $question['a'][$index] = sha1(getenv('CAPTCHA_SALT').$answer);
        }

        return $question;
    }

    public static function isCorrectAnswer(string $answer, string $hash): bool
    {
        return (sha1(getenv('CAPTCHA_SALT').md5(strtolower(trim($answer)))) === $hash);
    }
}
