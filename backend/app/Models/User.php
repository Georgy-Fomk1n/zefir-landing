<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'marketing_consent', 'pd_consent_at', 'telegram_chat_id', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    /** Значения по умолчанию совпадают с БД: без них у только что созданной модели is_active === null */
    protected $attributes = ['is_active' => true, 'marketing_consent' => false];

    /** @var list<string>|null */
    private ?array $roleCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'marketing_consent' => 'boolean',
            'pd_consent_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roleCache ??= DB::table('user_roles')->where('user_id', $this->id)->orderBy('role')->pluck('role')->all();
    }

    public function hasRole(Role ...$roles): bool
    {
        return array_intersect(array_map(fn (Role $r) => $r->value, $roles), $this->roles()) !== [];
    }

    public function isStaff(): bool
    {
        return $this->hasRole(...Role::staff());
    }

    /** @param list<Role> $roles */
    public function syncRoles(array $roles): void
    {
        DB::transaction(function () use ($roles) {
            DB::table('user_roles')->where('user_id', $this->id)->delete();
            DB::table('user_roles')->insert(array_map(fn (Role $r) => ['user_id' => $this->id, 'role' => $r->value], array_unique($roles, SORT_REGULAR)));
        });
        $this->roleCache = null;
    }

    public function addRole(Role $role): void
    {
        DB::table('user_roles')->insertOrIgnore(['user_id' => $this->id, 'role' => $role->value]);
        $this->roleCache = null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }
}
