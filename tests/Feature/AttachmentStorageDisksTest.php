<?php

use App\Models\BankStatementEntry;
use App\Models\ExpenseVoucher;
use App\Models\User;
use App\Services\BankRegister\BankEntryAttachmentService;
use App\Services\ExpenseVoucherAttachmentService;
use App\Services\SettlementAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets each module choose Amazon S3 or local on its own', function () {
    // Only sales settlement is switched to S3; vouchers and the bank register stay on the server.
    config(['settlement_attachments.disk' => 's3', 'voucher_attachments.disk' => 'local', 'bank.attachments.disk' => 'local']);
    Storage::fake('s3');
    Storage::fake('local');

    $settlementPath = app(SettlementAttachmentService::class)->store(UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'), 'SS-9', 'cheques');

    $voucher = ExpenseVoucher::factory()->create();
    $this->actingAs(User::factory()->create());
    app(ExpenseVoucherAttachmentService::class)->attach($voucher, [UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')]);
    $voucherPath = $voucher->attachments()->first()->file_path;

    $fixture = bankRegisterFixture();
    $entry = BankStatementEntry::factory()->create(['bank_account_id' => $fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 10, 'debit' => 0]);
    app(BankEntryAttachmentService::class)->attach($entry, [UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf')], 'receipt', null, bankUser(['bank-statement-entry-attach']));
    $bankPath = $entry->attachments()->first()->file_path;

    Storage::disk('s3')->assertExists($settlementPath);
    Storage::disk('s3')->assertMissing([$voucherPath, $bankPath]);
    Storage::disk('local')->assertExists([$voucherPath, $bankPath]);
    Storage::disk('local')->assertMissing($settlementPath);
});

it('can send voucher files to S3 while settlement and bank stay local', function () {
    config(['settlement_attachments.disk' => 'local', 'voucher_attachments.disk' => 's3', 'bank.attachments.disk' => 'local']);
    Storage::fake('s3');
    Storage::fake('local');

    $voucher = ExpenseVoucher::factory()->create();
    $this->actingAs(User::factory()->create());
    app(ExpenseVoucherAttachmentService::class)->attach($voucher, [UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')]);

    Storage::disk('s3')->assertExists($voucher->attachments()->first()->file_path);
    $settlementPath = app(SettlementAttachmentService::class)->store(UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'), 'SS-9', 'cheques');
    Storage::disk('local')->assertExists($settlementPath);
});
