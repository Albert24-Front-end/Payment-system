<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    const STATUS_ACTIVE = 0;
    const STATUS_BANNED = 1;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function checkPassword(string $password): void
    {
        if (!Hash::check($password, $this->password)) {
            throw new \Exception('Invalid password');
        }
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function terminals(): hasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        // в этот момент Laravel запрашивает у БД сначала роль, затем разрешения
        $permissions = $this->role?->permissions?->map(
            fn(Permission $permission) => $permission->name
        ) ?? collect([]);

        return $permissions->contains($permissionName);
    }

    #[Scope]
    protected function hasTerminal(Builder $query, int $terminalId): void
    {
        $query->select("users.*")->join("terminals", "terminals.user_id", "=", "users.id")
        ->where("terminals.id", "=", $terminalId);

        // $query->whereHas("terminals", fn($query) => $query->where("id", $terminalId)); // без join, но с подзапросом - иногда это медленнее - подзапрос делается для каждого юзера
        // select * from users where exists (select * from terminals where terminals.user_id = users.id and terminals.id = $terminalId)
    }

    #[Scope]
    protected function hasTerminalWithName(Builder $query, string $terminalName): void
    {
        $query->select("users.*")->join("terminals", "terminals.user_id", "=", "users.id")
            ->where("terminals.name", "=", $terminalName);

        // $query->whereHas("terminals", fn($query) => $query->where("name", $terminalName)); // без join, но с подзапросом - иногда это медленнее - подзапрос делается для каждого юзера
        // select * from users where exists (select * from terminals where terminals.user_id = users.id and terminals.name = $terminalName)
    }
}
