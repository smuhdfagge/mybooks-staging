<?php

namespace Tests\Feature\Auth;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class UncompromisedPasswordTest extends TestCase
{
    private const PASSWORD = 'Leaked-Passw0rd!';

    public function test_a_password_found_in_a_data_leak_is_rejected(): void
    {
        $hash = strtoupper(sha1(self::PASSWORD));
        $this->pwnedPasswordsBody = "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n".substr($hash, 5).":42\r\n";

        $validator = Validator::make(['password' => self::PASSWORD], ['password' => [Password::defaults()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['The given password has appeared in a data leak. Please choose a different password.'],
            $validator->errors()->get('password'),
        );
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));
    }

    public function test_a_password_not_in_the_range_response_passes(): void
    {
        $this->pwnedPasswordsBody = "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n";

        $validator = Validator::make(['password' => self::PASSWORD], ['password' => [Password::defaults()]]);

        $this->assertFalse($validator->fails());
    }
}
