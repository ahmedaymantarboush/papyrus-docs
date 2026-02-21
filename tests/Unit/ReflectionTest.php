<?php

use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

enum GenderEnum: string
{
    case Male = 'male';
    case Female = 'female';
}

class ReflectionController
{
    /**
     * Get Items
     *
     * Returns a list of items.
     *
     * @group Inventory
     */
    public function index()
    {
        return 'list';
    }

    public function store(ReflectionRequest $request)
    {
        return new ReflectionResource(['id' => 1]);
    }
}

class ReflectionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'count' => 'integer|min:1',
            'status' => 'required|in:active,inactive,pending',
            'avatar' => ['required', 'image'],
            'gender' => ['required', 'string', Rule::enum(GenderEnum::class)],
        ];
    }
}

class ReflectionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => 'Test Item',
        ];
    }
}

it('parses docblock summary and description', function () {
    Route::get('/reflection/index', [ReflectionController::class, 'index']);

    $generator = new PapyrusGenerator();
    $groups = $generator->scan();
    $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'reflection/index');

    expect($route->title)->toBe('Get Items');
    expect($route->description)->toBe("Returns a list of items.");
    expect($route->group)->toBe('Inventory');
});

it('extracts form request validation rules', function () {
    Route::post('/reflection/store', [ReflectionController::class, 'store']);

    $generator = new PapyrusGenerator();
    $groups = $generator->scan();
    $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'reflection/store');

    // bodyParams is now an array of node arrays with 'key' field
    $params = collect($route->bodyParams);

    $name = $params->firstWhere('key', 'name');
    expect($name)->not->toBeNull();
    expect($name['type'])->toBe('text');
    expect($name['required'])->toBeTrue();
    expect($name['rules'])->toContain('required');
    expect($name['rules'])->toContain('string');

    $count = $params->firstWhere('key', 'count');
    expect($count)->not->toBeNull();
    expect($count['type'])->toBe('number');

    // Enum extraction
    $status = $params->firstWhere('key', 'status');
    expect($status)->not->toBeNull();
    expect($status['type'])->toBe('select');
    expect($status['options'])->toBe(['active', 'inactive', 'pending']);
    expect($status['required'])->toBeTrue();

    // File detection
    $avatar = $params->firstWhere('key', 'avatar');
    expect($avatar)->not->toBeNull();
    expect($avatar['type'])->toBe('file');
    expect($avatar['required'])->toBeTrue();

    // Rule::enum() extraction — options from Enum.__toString()
    $gender = $params->firstWhere('key', 'gender');
    expect($gender)->not->toBeNull();
    expect($gender['type'])->toBe('select');
    expect($gender['options'])->toBe(['male', 'female']);
    expect($gender['required'])->toBeTrue();
});
