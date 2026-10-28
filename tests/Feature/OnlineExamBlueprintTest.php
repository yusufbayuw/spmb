<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamCategoryResource;
use App\Filament\Admin\Resources\OnlineExamCategoryResource\Pages\CreateOnlineExamCategory;
use App\Models\OnlineExam;
use App\Models\OnlineExamCategory;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamEngine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineExamBlueprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin_unit', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_blueprint_draws_quota_by_category_and_keeps_grading_server_side(): void
    {
        $unit = $this->unit('BLUE-A');
        $math = $this->category($unit, 'MAT');
        $logic = $this->category($unit, 'LOG');
        $this->question($math, 'easy', 'M1');
        $this->question($math, 'easy', 'M2');
        $this->question($math, 'hard', 'M3');
        $this->question($logic, 'medium', 'L1');
        $this->question($logic, 'medium', 'L2');

        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Acak',
            'blueprint' => [
                ['category_id' => $math->id, 'difficulty' => 'easy', 'count' => 2],
                ['category_id' => $logic->id, 'difficulty' => 'any', 'count' => 1],
            ],
        ]);
        $exam->update(['is_published' => true]);
        $staff = $this->staff($unit);
        $user = $this->participant();
        $attempt = app(OnlineExamEngine::class)->start(
            app(OnlineExamEngine::class)->assign($exam, $user, $staff), $user
        );
        $items = $attempt->items()->with('question')->get();
        $this->assertCount(3, $items);
        $this->assertSame(0, $exam->items()->count());
        $this->assertSame(3, $items->pluck('online_exam_question_id')->unique()->count());
        $this->assertSame(2, $items->filter(fn ($item) =>
            (int) $item->question->online_exam_category_id === $math->id
            && $item->question->difficulty === 'easy')->count());

        foreach ($items as $item) {
            $this->assertArrayNotHasKey('grading_snapshot', $item->toArray());
            app(OnlineExamEngine::class)->save($attempt, $user, $item->id, '1');
        }
        $this->assertEquals(100, (float) app(OnlineExamEngine::class)->submit($attempt, $user)->percentage);
    }

    public function test_publication_rejects_insufficient_pool_and_foreign_unit_category(): void
    {
        $unit = $this->unit('BLUE-B');
        $other = $this->unit('BLUE-C');
        $category = $this->category($unit, 'MAT');
        $foreign = $this->category($other, 'LOG');
        $this->question($category, 'medium', 'Satu');
        $this->question($foreign, 'medium', 'Asing');

        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Paket',
            'blueprint' => [['category_id' => $category->id, 'difficulty' => 'medium', 'count' => 2]],
        ]);
        try {
            $exam->update(['is_published' => true]);
            $this->fail('Insufficient pool must not publish.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('blueprint', $e->errors());
        }
        $exam->update(['blueprint' => [
            ['category_id' => $foreign->id, 'difficulty' => 'any', 'count' => 1],
        ]]);
        try {
            $exam->update(['is_published' => true]);
            $this->fail('Cross-unit blueprint must not publish.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('blueprint', $e->errors());
        }
        $this->assertFalse($exam->fresh()->is_published);
    }

    public function test_published_blueprint_locks_question_pool_and_category(): void
    {
        $unit = $this->unit('BLUE-D');
        $category = $this->category($unit, 'PHY');
        $question = $this->question($category, 'medium', 'Fisika');
        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Terkunci',
            'blueprint' => [['category_id' => $category->id, 'difficulty' => 'any', 'count' => 1]],
        ]);
        $exam->update(['is_published' => true]);
        try {
            $question->update(['is_active' => false]);
            $this->fail('Question pool may not change.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('online_exam_category_id', $e->errors());
        }
        try {
            $category->update(['is_active' => false]);
            $this->fail('Category may not deactivate.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        $exam->update(['is_published' => false]);
        $question->update(['is_active' => false]);
        $category->update(['is_active' => false]);
        $this->assertFalse($question->fresh()->is_active);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_legacy_manual_package_still_publishes_and_cannot_mix_modes(): void
    {
        $unit = $this->unit('BLUE-E');
        $category = $this->category($unit, 'SCI');
        $question = $this->question($category, 'medium', 'Manual');
        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Manual']);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id, 'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);
        $this->assertTrue($exam->fresh()->is_published);
        $exam->update(['is_published' => false, 'blueprint' => [
            ['category_id' => $category->id, 'difficulty' => 'any', 'count' => 1],
        ]]);
        try {
            $exam->update(['is_published' => true]);
            $this->fail('Mixing manual and blueprint questions must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('blueprint', $e->errors());
        }
    }

    public function test_category_filament_isolation_between_units(): void
    {
        $a = $this->unit('BLUE-F');
        $b = $this->unit('BLUE-G');
        $owned = $this->category($a, 'MAT');
        $this->category($b, 'MAT');
        $this->actingAs($this->staff($a));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertSame([$owned->id], OnlineExamCategoryResource::getEloquentQuery()->pluck('id')->all());
        Livewire::test(CreateOnlineExamCategory::class)->assertSuccessful();
    }

    private function unit(string $code): Unit
    {
        return Unit::create(['name' => 'Unit '.$code, 'code' => $code, 'is_active' => true]);
    }

    private function category(Unit $unit, string $code): OnlineExamCategory
    {
        return OnlineExamCategory::create([
            'unit_id' => $unit->id, 'code' => $code, 'name' => $code, 'is_active' => true,
        ]);
    }

    private function question(OnlineExamCategory $category, string $difficulty, string $prompt): OnlineExamQuestion
    {
        return OnlineExamQuestion::create([
            'unit_id' => $category->unit_id, 'online_exam_category_id' => $category->id,
            'difficulty' => $difficulty, 'type' => 'true_false', 'prompt' => $prompt,
            'options' => [], 'grading_config' => ['correct' => '1'], 'points' => 1, 'is_active' => true,
        ]);
    }

    private function staff(Unit $unit): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $user->assignRole('admin_unit');

        return $user;
    }

    private function participant(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');

        return $user;
    }
}
