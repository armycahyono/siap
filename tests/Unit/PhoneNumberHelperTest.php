<?php

namespace Tests\Unit;

use App\Models\PiketGuest;
use App\Models\Student;
use App\Models\User;
use App\Support\PhoneNumberHelper;
use PHPUnit\Framework\TestCase;

class PhoneNumberHelperTest extends TestCase
{
    public function test_format_phone_starting_with_zero(): void
    {
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('081234567890'));
        $this->assertEquals('6285712345678', PhoneNumberHelper::formatForWhatsapp('085712345678'));
    }

    public function test_format_phone_with_plus_62_and_dashes_spaces(): void
    {
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('+62 812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('0812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('+6281234567890'));
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('62 812 3456 7890'));
    }

    public function test_format_phone_starting_with_eight_without_zero(): void
    {
        $this->assertEquals('6281234567890', PhoneNumberHelper::formatForWhatsapp('81234567890'));
    }

    public function test_format_empty_or_invalid_phone(): void
    {
        $this->assertNull(PhoneNumberHelper::formatForWhatsapp(null));
        $this->assertNull(PhoneNumberHelper::formatForWhatsapp(''));
        $this->assertNull(PhoneNumberHelper::formatForWhatsapp('   '));
        $this->assertNull(PhoneNumberHelper::formatForWhatsapp('-'));
        $this->assertNull(PhoneNumberHelper::formatForWhatsapp('tidak ada'));
    }

    public function test_to_whatsapp_url(): void
    {
        $this->assertEquals('https://wa.me/6281234567890', PhoneNumberHelper::toWhatsappUrl('081234567890'));
        $this->assertEquals('https://wa.me/6281234567890?text=Halo+Bapak', PhoneNumberHelper::toWhatsappUrl('081234567890', 'Halo Bapak'));
        $this->assertNull(PhoneNumberHelper::toWhatsappUrl(null));
        $this->assertNull(PhoneNumberHelper::toWhatsappUrl('-'));
    }

    public function test_model_accessors_format_properly(): void
    {
        $user = new User(['phone' => '081234567890']);
        $this->assertEquals('6281234567890', $user->whatsapp_number);
        $this->assertEquals('https://wa.me/6281234567890', $user->whatsapp_url);

        $student = new Student([
            'phone_student' => '085711122233',
            'phone_parent' => '081399988877',
        ]);
        $this->assertEquals('6285711122233', $student->whatsapp_number_student);
        $this->assertEquals('https://wa.me/6285711122233', $student->whatsapp_url_student);
        $this->assertEquals('6281399988877', $student->whatsapp_number_parent);
        $this->assertEquals('https://wa.me/6281399988877', $student->whatsapp_url_parent);

        $guest = new PiketGuest(['phone' => '089876543210']);
        $this->assertEquals('6289876543210', $guest->whatsapp_number);
        $this->assertEquals('https://wa.me/6289876543210', $guest->whatsapp_url);
    }
}
