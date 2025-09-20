<?php

use App\Actions\Admin\Blog\Category\UpdateBlogCategoryAction;
use App\Data\Admin\Blog\Category\BlogCategoryUpdateData;

describe('UpdateBlogCategoryAction', function (): void {
   beforeEach(function (): void{
       $this->staff = \App\Models\Staff::factory()->create();
       Storage::fake('public');

       $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon1.jpg'))
           ->toDisk('public')
           ->upload();
       $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon2.jpg'))
           ->toDisk('public')
           ->upload();

       $this->category = \App\Models\Blog\BlogCategory::factory()->create([
           'name' => 'Original Category',
           'slug' => 'original-category',
           'description' => 'Original description',
           'parent_id' => null,
       ]);
       $this->category->attachMedia($this->media, 'icon');
   });

    it('updates a blog category', function (): void {
         $data = new BlogCategoryUpdateData(
              name: 'Updated Category',
              slug: 'updated-category',
              description: 'Updated description',
              parent_id: null,
              icon: $this->media2->id,
         );

         $action = new UpdateBlogCategoryAction();
         $updatedCategory = $action->handle($this->category, $data);

         expect($updatedCategory)->toBeInstanceOf(\App\Models\Blog\BlogCategory::class)
             ->and($updatedCategory->name)->toBe('Updated Category')
             ->and($updatedCategory->slug)->toBe('updated-category')
             ->and($updatedCategory->description)->toBe('Updated description')
             ->and($updatedCategory->parent_id)->toBeNull()
             ->and($updatedCategory->firstMedia('icon')->getUrl())->toBe($this->media2->getUrl());
    });

    it('updates a blog category and removing the icon', function (): void {
         $data = new BlogCategoryUpdateData(
              name: 'Updated Category No Icon Change',
              slug: null,
              description: 'Updated description no icon change',
              parent_id: null,
              icon: null,
         );

         $action = new UpdateBlogCategoryAction();
         $updatedCategory = $action->handle($this->category, $data);
            expect($updatedCategory)->toBeInstanceOf(\App\Models\Blog\BlogCategory::class)
                ->and($updatedCategory->name)->toBe('Updated Category No Icon Change')
                ->and($updatedCategory->slug)->toBe('updated-category-no-icon-change')
                ->and($updatedCategory->description)->toBe('Updated description no icon change')
                ->and($updatedCategory->parent_id)->toBeNull()
                ->and($updatedCategory->firstMedia('icon'))->toBeNull();
    });
});
