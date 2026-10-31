<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'chat_id',
        'description',
        'invite_link',
        'allowed_roles',
        'enabled_commands',
        'is_active',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'enabled_commands' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Dapatkan channel berdasarkan kodenya.
     */
    public static function findByCode(string $code): ?self
    {
        return self::where('code', $code)->first();
    }

    /**
     * Dapatkan Chat ID grup berdasarkan kodenya.
     */
    public static function getChatId(string $code): ?string
    {
        $channel = self::findByCode($code);
        if ($channel && $channel->is_active && ! empty($channel->chat_id)) {
            return $channel->chat_id;
        }

        // Fallback ke SchoolSetting jika belum diisi di TelegramChannel
        $setting = SchoolSetting::first();
        if (! $setting) {
            return null;
        }

        return match ($code) {
            'discipline' => $setting->telegram_discipline_chat_id,
            'piket' => $setting->telegram_piket_chat_id,
            'supervision' => $setting->telegram_supervision_chat_id,
            'activities' => $setting->telegram_activities_chat_id,
            'gtk' => $setting->telegram_teachers_chat_id,
            default => null,
        };
    }

    /**
     * Dapatkan daftar user/GTK yang memenuhi syarat bergabung ke grup ini berdasarkan allowed_roles.
     */
    public function getEligibleUsers(): Collection
    {
        $roles = $this->allowed_roles ?? [];
        if (empty($roles)) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        return User::where('is_active', true)
            ->where(function ($q) use ($roles) {
                $q->whereHas('role', fn ($sub) => $sub->whereIn('slug', $roles))
                    ->orWhereHas('roleAssignments.role', fn ($sub) => $sub->whereIn('slug', $roles));

                // Jika grup mengizinkan Wali Kelas, sertakan guru yang tercatat sebagai wali kelas aktif di tabel classrooms
                if (in_array('wali_kelas', $roles)) {
                    $q->orWhereIn('id', Classroom::whereNotNull('homeroom_teacher_id')->pluck('homeroom_teacher_id'));
                }

                // Jika grup mengizinkan Guru Piket, sertakan guru yang memiliki jadwal piket aktif
                if (in_array('guru_piket', $roles) || in_array('piket_officer', $roles)) {
                    $q->orWhereIn('id', PiketSchedule::where('is_active', true)->pluck('teacher_id'));
                }
            })
            ->orderBy('name')
            ->get();
    }
}
