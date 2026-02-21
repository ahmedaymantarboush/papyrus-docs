<?php

use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

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

    // bodyParams is now enriched: key => { rules, type, required, options }
    expect($route->bodyParams)->toHaveKey('name');
    expect($route->bodyParams['name']['type'])->toBe('string');
    expect($route->bodyParams['name']['required'])->toBeTrue();
    expect($route->bodyParams['name']['rules'])->toContain('required');
    expect($route->bodyParams['name']['rules'])->toContain('string');

    expect($route->bodyParams)->toHaveKey('count');
    expect($route->bodyParams['count']['type'])->toBe('number');

    // Enum extraction
    expect($route->bodyParams)->toHaveKey('status');
    expect($route->bodyParams['status']['type'])->toBe('select');
    expect($route->bodyParams['status']['options'])->toBe(['active', 'inactive', 'pending']);
    expect($route->bodyParams['status']['required'])->toBeTrue();

    // File detection
    expect($route->bodyParams)->toHaveKey('avatar');
    expect($route->bodyParams['avatar']['type'])->toBe('file');
    expect($route->bodyParams['avatar']['required'])->toBeTrue();
});
