<?php
describe('DeleteBlogPostAction', function () {
    beforeEach(function (){
        $this->staff = \App\Models\Staff::factory()->create();
        Storage::fake('public');

        $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('post-image.jpg'))
            ->toDisk('public')
            ->upload();

        $this->post = \App\Models\Blog\BlogPost::factory()->create([
            'title' => 'Sample Post',
            'slug' => 'sample-post',
            'author_id' => $this->staff->id,
        ]);
        $this->post->attachMedia($this->media, 'images');
    });

    it('deletes a blog post and its media', function () {
        $action = new \App\Actions\Admin\Blog\Post\DeleteBlogPostAction();
        $action->handle($this->post);

        expect(\App\Models\Blog\BlogPost::find($this->post->id))->toBeNull()
            ->and($this->post->media()->count())->toBe(0);
    });
});
