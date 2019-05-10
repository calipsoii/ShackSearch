<?php

namespace App\Chatty\Contracts;

use Illuminate\Support\Collection;

Interface SearchContract
{
    public function createChattyPostIndex();
    public function searchCollection($queryString): Collection;
    public function searchArray($queryString): array;
    public function getPostsForTerm($term,$author,$from,$to);
    public function simpleQueryStringWithSuggestions($body,$author,$rootPosts,$from,$to): array;
    public function matchQueryWithSuggestions($body,$author,$rootPosts,$from,$to): array;
    public function commonTermsQueryWithSuggestions($body,$author,$rootPosts,$from,$to): array;
    public function termVectorsForAuthor($author,$from,$to);
    public function termVectorsWithScoreForPostIds($postIds);
    public function termVectorsForPost($postId);
    public function termVectorsForPostWithScore($postId);
    public function generatePostTrigramsForTerm($termArr, $author, $from, $to);
    public function getTrigramsForPost($postID);
    public function indexPost($post);
}