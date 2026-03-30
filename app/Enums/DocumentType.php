<?php

namespace App\Enums;

enum DocumentType: string
{
    case Md = 'MD';
    case Dr = 'DR';
    case Dz = 'DZ';
    case Za = 'ZA';
    case Dg = 'DG';
    case Mc = 'MC';
    case Ab = 'AB';
    case Mr = 'MR';
    case Zd = 'ZD';
    case Mi = 'MI';
    case Ob = 'OB';

    public function label(): string
    {
        return match ($this) {
            self::Md => 'Extra Tax',
            self::Dr => 'Invoices',
            self::Dz => 'Online',
            self::Za => '0.50%',
            self::Dg => 'Claims',
            self::Mc => 'Manual Claims',
            self::Ab => 'Payment from Unidentified Account',
            self::Mr => 'Abnormalities',
            self::Zd => 'Cash Discount/Rates Diff',
            self::Mi => 'Miscellaneous',
            self::Ob => 'Opening Balance',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Md => 'bg-purple-100 text-purple-800',
            self::Dr => 'bg-blue-100 text-blue-800',
            self::Dz => 'bg-green-100 text-green-800',
            self::Za => 'bg-yellow-100 text-yellow-800',
            self::Dg => 'bg-orange-100 text-orange-800',
            self::Mc => 'bg-red-100 text-red-800',
            self::Ab => 'bg-cyan-100 text-cyan-800',
            self::Mr => 'bg-pink-100 text-pink-800',
            self::Zd => 'bg-indigo-100 text-indigo-800',
            self::Mi => 'bg-gray-100 text-gray-800',
            self::Ob => 'bg-amber-100 text-amber-800',
        };
    }
}
