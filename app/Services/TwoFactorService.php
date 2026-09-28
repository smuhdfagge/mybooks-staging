<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    /**
     * Generate a new secret key for the user.
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Generate a QR code SVG for the user to scan.
     */
    public function generateQrCodeSvg(User $user, string $secret): string
    {
        $companyName = config('app.name', 'MyBooks');

        $qrCodeUrl = $this->engine->getQRCodeUrl(
            $companyName,
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        $rawSvg = $writer->writeString($qrCodeUrl);

        // Sanitize SVG output to strip any potential XSS vectors
        return SvgSanitizer::sanitize($rawSvg);
    }

    /**
     * Verify a TOTP code against the user's secret.
     */
    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code);
    }

    /**
     * Generate recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(sprintf(
                '%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(4))
            ));
        }

        return $codes;
    }

    /**
     * Get the decrypted secret from a user.
     */
    public function getDecryptedSecret(User $user): ?string
    {
        if (empty($user->two_factor_secret)) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }

    /**
     * Get decrypted recovery codes from a user.
     */
    public function getRecoveryCodes(User $user): array
    {
        if (empty($user->two_factor_recovery_codes)) {
            return [];
        }

        return json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
    }
}
