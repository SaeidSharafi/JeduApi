<?php

describe('DeleteBlogCategoryAction', function () {
   beforeEach(function (){
       $this->staff = \App\Models\Staff::factory()->create();
       Storage::fake('public');

       $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon1.jpg'))
           ->toDisk('public')
           ->upload();
       $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon2.jpg'))
           ->toDisk('public')
           ->upload();

       $this->category = \App\Models\Blog\BlogCategory::factory()->create([
           'name' => 'Category to Delete',
           'slug' => 'category-to-delete',
           'description' => 'Description of category to delete',
           'parent_id' => null,
       ]);
       $this->category->attachMedia($this->media, 'icon');
   });

    it('deletes a blog category', function () {
         $action = new \App\Actions\Admin\Blog\Category\DeleteBlogCategoryAction();
         $action->handle($this->category);

         expect(\App\Models\Blog\BlogCategory::find($this->category->id))->toBeNull()
             ->and($this->category->media()->count())->toBe(0);
    });
});
