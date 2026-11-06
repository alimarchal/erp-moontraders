<?php

use App\Models\Attachment;
use App\Models\BankStatementEntry;
use App\Services\AttachmentImageOptimizer;
use App\Services\BankRegister\BankStatementImportService;
use App\Services\SettlementAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['attachment_images.enabled' => true, 'attachment_images.max_px' => 800, 'attachment_images.min_kb' => 300]);
});

it('shrinks a large photo, keeping its name and type', function () {
    $original = UploadedFile::fake()->image('receipt.jpg', 2400, 1600);

    $result = app(AttachmentImageOptimizer::class)->optimize($original);
    [$width, $height] = getimagesize($result->getRealPath());

    expect($result->getClientOriginalName())->toBe('receipt.jpg')
        ->and(max($width, $height))->toBe(800)
        ->and($width / $height)->toBeGreaterThan(1.45)->toBeLessThan(1.55);
});

it('leaves a small photo, a PDF and a disabled setting alone', function () {
    $optimizer = app(AttachmentImageOptimizer::class);
    $small = UploadedFile::fake()->image('small.png', 300, 200);
    $pdf = UploadedFile::fake()->create('slip.pdf', 900, 'application/pdf');

    expect($optimizer->optimize($small))->toBe($small)->and($optimizer->optimize($pdf))->toBe($pdf);

    config(['attachment_images.enabled' => false]);
    $big = UploadedFile::fake()->image('big.jpg', 2400, 1600);
    expect($optimizer->optimize($big))->toBe($big);
});

it('keeps the original when the file is not a real picture', function () {
    $broken = UploadedFile::fake()->createWithContent('scan.jpg', 'this is not an image');

    expect(app(AttachmentImageOptimizer::class)->optimize($broken))->toBe($broken);
});

it('shrinks a settlement attachment before it is stored', function () {
    Storage::fake(SettlementAttachmentService::disk());

    $path = app(SettlementAttachmentService::class)->store(UploadedFile::fake()->image('slip.jpg', 2400, 1600), 'SS-1', 'cheques');
    $stored = Storage::disk(SettlementAttachmentService::disk())->get($path);

    expect(max(...array_slice(getimagesizefromstring($stored), 0, 2)))->toBe(800);
});

it('stores bank register attachments on S3 when BANK_ATTACHMENT_DISK is s3, shrunk, and serves them back', function () {
    config(['bank.attachments.disk' => 's3']);
    Storage::fake('s3');
    $fixture = bankRegisterFixture();
    $maker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-attach'], 'Maker');
    $entry = BankStatementEntry::factory()->create(['bank_account_id' => $fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 1000, 'debit' => 0]);

    $this->actingAs($maker)->post(route('bank-statement-entries.attachments.store', $entry), [
        'files' => [UploadedFile::fake()->image('cheque.jpg', 2400, 1600), UploadedFile::fake()->create('slip.pdf', 100, 'application/pdf')],
        'label' => 'cheque',
    ])->assertSessionHas('success');

    $photo = Attachment::where('file_name', 'cheque.jpg')->firstOrFail();
    Storage::disk('s3')->assertExists($photo->file_path);
    expect(max(...array_slice(getimagesizefromstring(Storage::disk('s3')->get($photo->file_path)), 0, 2)))->toBe(800)
        ->and($photo->file_size)->toBeLessThan(500 * 1024);

    $this->get(route('bank-attachments.download', ['attachment' => $photo, 'inline' => 1]))->assertOk();
    $this->actingAs(bankUser(['bank-reconciliation-list']))->get(route('bank-attachments.download', $photo))->assertForbidden();
});

it('reads a bank statement from S3 through a temporary copy', function () {
    config(['bank.attachments.disk' => 's3']);
    Storage::fake('s3');
    $fixture = bankRegisterFixture();
    $csv = UploadedFile::fake()->createWithContent('statement.csv', "Date,Reference,Narration,Debit,Credit\n2026-07-01,CHQ-1,Test receipt,0,5000\n");

    $import = app(BankStatementImportService::class)->preview($fixture['bank'], $csv, [], bankUser(['bank-statement-import-create'], 'Importer'));

    Storage::disk('s3')->assertExists($import->file_path);
    expect($import->status)->toBe('previewed')->and($import->preview['valid_rows'])->toBe(1);
});

it('keeps a picture as it is when decoding it would not fit in the available memory', function () {
    $picture = UploadedFile::fake()->image('huge.jpg', 3000, 3000);
    $original = ini_get('memory_limit');
    ini_set('memory_limit', (int) ((memory_get_usage() / 1048576) + 40).'M');

    try {
        $result = app(AttachmentImageOptimizer::class)->optimize($picture);
    } finally {
        ini_set('memory_limit', $original);
    }

    expect($result)->toBe($picture);
});

it('saves a large opaque PNG as a much smaller JPEG with the matching name', function () {
    $path = tempnam(sys_get_temp_dir(), 'png-').'.png';
    $image = imagecreatetruecolor(1200, 900);
    for ($i = 0; $i < 400; $i++) {
        imagefilledrectangle($image, ($i * 37) % 1100, ($i * 53) % 800, ($i * 37) % 1100 + 120, ($i * 53) % 800 + 90, imagecolorallocate($image, ($i * 7) % 255, ($i * 11) % 255, ($i * 13) % 255));
    }
    imagepng($image, $path, 0);
    $png = new UploadedFile($path, 'scan.png', 'image/png', null, true);

    $result = app(AttachmentImageOptimizer::class)->optimize($png);

    expect($result->getClientOriginalName())->toBe('scan.jpg')
        ->and($result->getClientMimeType())->toBe('image/jpeg')
        ->and($result->getSize())->toBeLessThan($png->getSize() * 0.5);
});

it('flattens a see-through PNG onto white when it becomes a JPEG', function () {
    $path = tempnam(sys_get_temp_dir(), 'png-').'.png';
    $image = imagecreatetruecolor(1200, 900);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 10, 20, 30, 127));
    for ($i = 0; $i < 300; $i++) {
        imagefilledrectangle($image, 300 + ($i * 7) % 600, 250 + ($i * 5) % 400, 340 + ($i * 7) % 600, 290 + ($i * 5) % 400, imagecolorallocate($image, ($i * 7) % 255, ($i * 11) % 255, ($i * 13) % 255));
    }
    imagepng($image, $path, 0);

    $result = app(AttachmentImageOptimizer::class)->optimize(new UploadedFile($path, 'logo.png', 'image/png', null, true));
    $flat = imagecreatefromjpeg($result->getRealPath());
    $corner = imagecolorsforindex($flat, imagecolorat($flat, 5, 5));

    expect($result->getClientOriginalName())->toBe('logo.jpg')
        ->and($corner['red'])->toBeGreaterThan(240)->and($corner['green'])->toBeGreaterThan(240)->and($corner['blue'])->toBeGreaterThan(240);
});
