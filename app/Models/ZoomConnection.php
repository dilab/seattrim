<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Zoom\Data\TokenSet;
use Database\Factories\ZoomConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $zoom_account_id
 * @property string|null $installer_zoom_user_id
 * @property string|null $installer_email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property array<int, string>|null $scopes
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $connected_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_refreshed_at
 */
#[Fillable(['organization_id', 'zoom_account_id', 'installer_zoom_user_id', 'installer_email', 'access_token', 'refresh_token', 'expires_at', 'scopes', 'status', 'last_error', 'connected_at', 'revoked_at', 'last_refreshed_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class ZoomConnection extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ZoomConnectionFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'connected_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /** @return array<int, string> Scopes from config that this connection was not granted. */
    public function missingScopes(): array
    {
        /** @var array<int, string> $required */
        $required = config('zoom.scopes', []);

        return array_values(array_diff($required, $this->scopes ?? []));
    }

    public function storeTokens(TokenSet $tokens): void
    {
        $this->forceFill([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'expires_at' => now()->addSeconds($tokens->expiresIn),
            'scopes' => $tokens->scopes(),
            'last_refreshed_at' => now(),
            'status' => self::STATUS_ACTIVE,
            'last_error' => null,
        ])->save();
    }

    public function markRevoked(?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'last_error' => $reason,
            'access_token' => null,
            'refresh_token' => null,
        ])->save();
    }

    public function markError(string $message): void
    {
        $this->forceFill(['status' => self::STATUS_ERROR, 'last_error' => mb_substr($message, 0, 2000)])->save();
    }

    /** Never log tokens: only expose a redacted description. */
    public function describe(): string
    {
        return sprintf('zoom-connection#%d(account=%s,status=%s)', $this->id, $this->zoom_account_id, $this->status);
    }
}
