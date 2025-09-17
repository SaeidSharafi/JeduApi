<?php

use App\Actions\Admin\Blog\Category\CreateBlogCategoryAction;

describe('CreateBlogCategoryAction', function () {
   beforeEach(function (){
       $this->staff = \App\Models\Staff::factory()->create();
       Storage::fake('public');

       $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon1.jpg'))
           ->toDisk('public')
           ->upload();
       $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon2.jpg'))
           ->toDisk('public')
           ->upload();
   });

    it('creates a blog category', function () {
         $data = new \App\Data\Admin\Blog\Category\BlogCategoryCreateData(
              name: 'New Category',
              slug: 'new-category',
              description: 'A description for the new category',
              parent_id: null,
              icon: $this->media->id,
         );

         $action = new CreateBlogCategoryAction();
         $category = $action->handle($data);

         expect($category)->toBeInstanceOf(\App\Models\Blog\BlogCategory::class)
             ->and($category->name)->toBe('New Category')
             ->and($category->slug)->toBe('new-category')
             ->and($category->description)->toBe('A description for the new category')
             ->and($category->parent_id)->toBeNull()
             ->and($category->firstMedia('icon')->getUrl())->toBe($this->media->getUrl());
    });

    it('creates a blog category with a parent', function () {
         $parentCategory = \App\Models\Blog\BlogCategory::factory()->create();

         $data = new \App\Data\Admin\Blog\Category\BlogCategoryCreateData(
              name: 'Child Category',
              slug: 'child-category',
              description: 'A description for the child category',
              parent_id: $parentCategory->id,
              icon: $this->media2->id,
         );

         $action = new CreateBlogCategoryAction();
         $category = $action->handle($data);

         expect($category)->toBeInstanceOf(\App\Models\Blog\BlogCategory::class)
             ->and($category->name)->toBe('Child Category')
             ->and($category->slug)->toBe('child-category')
             ->and($category->description)->toBe('A description for the child category')
             ->and($category->parent_id)->toBe($parentCategory->id)
             ->and($category->firstMedia('icon')->getUrl())->toBe($this->media2->getUrl());
    });

    it('creates a blog category without an icon', function () {
         $data = new \App\Data\Admin\Blog\Category\BlogCategoryCreateData(
              name: 'No Icon Category',
              slug: 'no-icon-category',
              description: 'A description for the no icon category',
              parent_id: null,
              icon: null,
         );

         $action = new CreateBlogCategoryAction();
         $category = $action->handle($data);

         expect($category)->toBeInstanceOf(\App\Models\Blog\BlogCategory::class)
             ->and($category->name)->toBe('No Icon Category')
             ->and($category->slug)->toBe('no-icon-category')
             ->and($category->description)->toBe('A description for the no icon category')
             ->and($category->parent_id)->toBeNull()
             ->and($category->firstMedia('icon'))->toBeNull();
    });


});
