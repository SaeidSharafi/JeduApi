<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('local')->makeDirectory('forms');
    config()->set('services.moodle.base_url', 'https://lms.example.com');
    $this->pdf = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()
        ->create('attachment.pdf', 100, 'application/pdf'))
        ->toDisk('local')
        ->upload();
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $this->gallery = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('gallery.jpg'))
        ->toDisk('public')
        ->upload();
});

test('product createion with all combinations',
    function (ProductableEnum $productableType, string $fulfillmentTypeEnum, string $deliveryType, array $data): void {
        $this->admin_user();
        $categoryData = Category::factory()->make([
            'name' => 'Category 1',
            'slug' => 'category-1',
        ]);
        $response = postJson(route('api.v1.admin.categories.store'), $categoryData->toArray());
        $response->assertCreated();
        assertDatabaseCount('categories', 1);
        assertDatabaseHas('categories', [
            'name' => 'Category 1',
            'slug' => 'category-1',
        ]);
        $category        = Category::first();
        $ditialAssetData = DigitalAsset::factory()->make();
        $response        = postJson(route('api.v1.admin.digital-assets.store'),
            [
                ...$ditialAssetData->toArray(),
                'slug'         => 'digital-asset-1',
                'full_name'    => 'Digital Asset 1',
                'published_at' => '1403-01-01 00:00:00',
                'categories'   => [$category->id],
                'attachments'  => [
                    'main' => $this->pdf->id,
                ],
                'media' => [
                    'gallery'     => [$this->gallery->id],
                    'thumbnail'   => [],
                    'cover'       => [$this->cover->id],
                    'certificate' => [],
                ],
            ]
        );
        $response->assertCreated();
        assertDatabaseCount('digital_assets', 1);
        assertDatabaseHas('digital_assets', [
            'full_name' => 'Digital Asset 1',
            'slug'      => 'digital-asset-1',
        ]);
        $ditialAsset     = DigitalAsset::first();
        $productabelData = createProdutable($productableType);
        $route           = match ($productableType) {
            ProductableEnum::COURSE        => 'api.v1.admin.courses.store',
            ProductableEnum::DIGITAL_ASSET => 'api.v1.admin.digital-assets.store',
            ProductableEnum::SEMINAR       => 'api.v1.admin.seminars.store',
        };
        $productabelData = [
            ...$productabelData,
            'categories'     => [$category->id],
            'digital_assets' => [$ditialAsset->id],
            'media'          => [
                'gallery'     => [$this->gallery->id],
                'thumbnail'   => [],
                'cover'       => [$this->cover->id],
                'certificate' => [],
            ],
        ];
        if ($productableType === ProductableEnum::DIGITAL_ASSET) {
            $productabelData['attachments'] = [
                'main' => $this->pdf->id,
            ];
        }
        $response = $this->postJson(route($route), $productabelData);
        $response->assertCreated();
        $table = match ($productableType) {
            ProductableEnum::COURSE        => 'courses',
            ProductableEnum::DIGITAL_ASSET => 'digital_assets',
            ProductableEnum::SEMINAR       => 'seminars',
        };
        assertDatabaseCount($table, $productableType === ProductableEnum::DIGITAL_ASSET ? 2 : 1);
        assertDatabaseHas($table, [
            'full_name' => $productabelData['full_name'],
            'slug'      => $productabelData['slug'],
        ]);

        $productable = Illuminate\Support\Facades\DB::table($table)->first();
        $vendor      = Vendor::factory()->create();
        $term        = Term::factory()->create();
        $response    = postJson(route('api.v1.admin.products.store'), [
            'force_create'     => false,
            'name'             => 'Product 1',
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'is_visible'       => true,
            'is_featured'      => false,
            'description'      => 'Description for product 1',
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'categories'       => [$category->id],
            'productable_type' => $productableType->value,
            'productable_id'   => $productable->id,
            'details_json'     => [],
        ]);
        $response->assertCreated();

        assertDatabaseCount('products', 1);
        assertDatabaseHas('products', [
            'name'             => 'Product 1',
            'slug'             => $productable->slug,
            'productable_type' => $productableType->value,
            'productable_id'   => $productable->id,
        ]);
        $product  = Product::first();
        $response = postJson(route('api.v1.admin.delivery-options.store', $product->id), [
            'name'                    => 'Delivery Option 1',
            'is_prepayment_available' => false,
            'is_featured'             => false,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => $fulfillmentTypeEnum,
            'delivery_method'         => $deliveryType,
            'details'                 => $data,
            'price'                   => 10000,
            'schedule_days'           => ['sun', 'mon'],
            'start_date'              => verta()->addDays(7)->formatDate(),
            'duration'                => 60,
            'teachers'                => [
                Teacher::factory()->create()->id,
            ],
        ]);
        $response->assertCreated();
        assertDatabaseCount('product_delivery_options', 1);
        assertDatabaseHas('product_delivery_options', [
            'product_id'       => $product->id,
            'fulfillment_type' => $fulfillmentTypeEnum,
            'delivery_method'  => $deliveryType,
        ]);

        $deliveryOption = ProductDeliveryOption::first();

        $items = [
            [
                'product_delivery_option_id' => $deliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'price'                      => $deliveryOption->price,
                'total'                      => $deliveryOption->price,
                'name'                       => 'Test Course',
            ],
        ];
        $customer = User::factory()->create();
        $order    = Order::factory()
            ->withCalculatedTotals($items)
            ->create(['customer_id' => $customer->id])
            ->fresh();
        $enrollment = Enrollment::factory()->create([
            'order_id'                   => $order->id,
            'order_item_id'              => $order->items()->first()->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE->value,
            'provisioning_data'          => ['providers' => getProvisioningData($data, $deliveryType)],
        ]);
        $this->customer($customer);
        $response = getJson(route('api.v1.shop.student.courses.show', $enrollment->uuid));
        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'uuid',
                'enrollment_status' => [
                    'value',
                    'label',
                ],
                'access_start_date',
                'access_end_date',
                'notes',
                'product',
                'teachers' => [
                    '*' => [
                        'uuid',
                        'first_name',
                        'last_name',
                        'bio',
                        'avatar_url',
                        'rate',
                        'gender',
                        'social_links',
                    ],
                ],
                'review_info' => [
                    'has_reviewed',
                    'review',
                ],
                'certificate_info' => [
                    'is_available',
                    'certificate_url',
                ],
                'survey_block' => [
                    'url',
                    'message',
                ],
                'delivery_access',
            ],
        ]);

        $responseData = $response->json('data');
        expect($responseData['uuid'])->toBe($enrollment->uuid)
            ->and($responseData['delivery_access'])->toEqual(getDeliveryBlock($data, $deliveryType, $enrollment,
                $ditialAsset));

    })->with('valid product');

function getDeliveryBlock(
    array $data,
    string $deliveryMethod,
    Enrollment $enrollment,
    DigitalAsset $digitalAsset
): ?array {
    return match ($deliveryMethod) {
        'direct_download' => [
            'id'            => $digitalAsset->id,
            'short_name'    => $digitalAsset->short_name,
            'full_name'     => $digitalAsset->full_name,
            'thumbnail_url' => $digitalAsset->thumbnail_url,
            'download_url'  => route('api.v1.shop.student.digital-assets.download',
                ['enrollment' => $enrollment->uuid, 'digitalAsset' => $digitalAsset->id], absolute: true),

        ],
        'live_session_bbb', 'live_session_skyroom' => [
            'type'          => $deliveryMethod,
            'is_ready'      => false,
            'session_label' => 'کلاس آنلاین',
            'join_url_path' => '/api/v1/shop/my-courses/'.$enrollment->uuid.'/join',
            'course_url'    => null,
            'completed'     => null,
            'course_grade'  => null,
            'license_key'   => null,
            'player_url'    => null,
            'address'       => null,
            'map_url'       => null,
        ],
        'lms_moodle' => [
            'type'          => $deliveryMethod,
            'is_ready'      => filled($data['moodle_course_id'] ?? null),
            'session_label' => null,
            'join_url_path' => null,
            'course_url'    => null,
            'completed'     => false,
            'course_grade'  => null,
            'license_key'   => null,
            'player_url'    => null,
            'address'       => null,
            'map_url'       => null,
        ],
        'video_platform_spotplayer' => [
            'type'          => $deliveryMethod,
            'is_ready'      => true,
            'session_label' => null,
            'join_url_path' => null,
            'course_url'    => null,
            'completed'     => null,
            'course_grade'  => null,
            'license_key'   => 'XYZ',
            'player_url'    => 'spotplayer.example.com/player/12345',
            'address'       => null,
            'map_url'       => null,
        ],
        'in_person' => [
            'type'          => $deliveryMethod,
            'is_ready'      => true,
            'session_label' => null,
            'join_url_path' => null,
            'course_url'    => null,
            'completed'     => null,
            'course_grade'  => null,
            'license_key'   => null,
            'player_url'    => null,
            'address'       => $data['address'],
            'map_url'       => $data['map_url'] ?? null,
        ],
        default => [],
    };
}

function getProvisioningData(
    array $data,
    string $deliveryMethod
): array {

    return match ($deliveryMethod) {
        'lms_moodle' => [
            'moodle' => [
                'status' => 'success',
                'data'   => [
                    'moodle_course_id' => $data['moodle_course_id'],
                    'moodle_user_id'   => 123,
                ],
            ],
        ],
        'video_platform_spotplayer' => [
            'spotplayer' => [
                'status' => 'success',
                'data'   => [
                    'license_key' => 'XYZ',
                    'player_url'  => 'spotplayer.example.com/player/12345',
                ],
            ],
        ],
        default => [],
    };
}

function createProdutable(ProductableEnum $productableType): array
{
    return match ($productableType) {
        ProductableEnum::DIGITAL_ASSET => array_merge(DigitalAsset::factory()->make()->toArray(),
            ['published_at' => '1403-01-01 00:00:00']),
        ProductableEnum::COURSE  => Course::factory()->make()->toArray(),
        ProductableEnum::SEMINAR => Seminar::factory()->make()->toArray(),
    };
}

dataset('valid product', [
    // [
    //    ProductableEnum::DIGITAL_ASSET,
    //    'digital',
    //    'direct_download',
    //    [
    //        'max_downloads' => 5,
    //    ],
    // ],
    // [
    //    ProductableEnum::SEMINAR,
    //    'online_service',
    //    'live_session_bbb',
    //    [
    //        'session_url'      => 'https://bbb.example.com/meetings/12345',
    //        'session_id'       => '12345',
    //        'session_password' => 'password',
    //        'start_time'       => now()->addDays(7)->toDateTimeString(),
    //        'end_time'         => now()->addDays(7)->addHour()->toDateTimeString(),
    //    ],
    // ],
    // [
    //    ProductableEnum::SEMINAR,
    //    'online_service',
    //    'live_session_skyroom',
    //    [
    //        'session_url'      => 'https://skyroom.example.com/meetings/12345',
    //        'session_id'       => '12345',
    //        'session_password' => 'password',
    //        'start_time'       => now()->addDays(7)->toDateTimeString(),
    //        'end_time'         => now()->addDays(7)->addHour()->toDateTimeString(),
    //    ],
    // ],
    [
        ProductableEnum::COURSE,
        'online_service',
        'lms_moodle',
        [
            'moodle_course_id' => '12345',
        ],
    ],
    // [
    //    ProductableEnum::COURSE,
    //    'offline_service',
    //    'video_platform_spotplayer',
    //    [
    //        'spot_id'  => '12345',
    //        'access_key' => 'access_key',
    //    ],
    // ],
    // [
    //    ProductableEnum::COURSE,
    //    'in_person_service',
    //    'in_person',
    //    [
    //        'address' => '123 Main St, Anytown, USA',
    //        'map_url' => 'https://maps.google.com/?q=123+Main+St,+Anytown,+USA',
    //    ],
    // ],
]);

dataset('valid product only requried', [
    [
        'digital',
        'direct_download',
        [
            'max_downloads' => 5,
        ],
    ],
    [
        'online_service',
        'live_session_bbb',
        [
            'session_url'      => 'https://bbb.example.com/meetings/12345',
            'session_id'       => '12345',
            'session_password' => 'password',
            'start_time'       => now()->addDays(7)->toDateTimeString(),
            'end_time'         => now()->addDays(7)->addHour()->toDateTimeString(),
        ],
    ],
    [
        'online_service',
        'live_session_skyroom',
        [
            'session_url'      => 'https://skyroom.example.com/meetings/12345',
            'session_id'       => '12345',
            'session_password' => 'password',
            'start_time'       => now()->addDays(7)->toDateTimeString(),
            'end_time'         => now()->addDays(7)->addHour()->toDateTimeString(),
        ],
    ],
    [
        'online_service',
        'lms_moodle',
        [
            'course_url'     => 'https://moodle.example.com/course/12345',
            'course_id'      => '12345',
            'enrollment_key' => 'enrollment_key',
        ],
    ],
    [
        'offline_service',
        'video_platform_spotplayer',
        [
            'video_url'  => 'https://spotplayer.example.com/videos/12345',
            'access_key' => 'access_key',
        ],
    ],
    [
        'in_person_service',
        'in_person',
        [
            'address' => '123 Main St, Anytown, USA',
        ],
    ],
]);
