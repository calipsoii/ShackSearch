<?php

namespace App\Chatty;

use DB;
use App\Chatty\post;
use App\Chatty\app_setting;
use App\Chatty\postcategory;
use App\Chatty\Contracts\SearchContract;
use Illuminate\Support\Collection;
use Elasticsearch\Client;

class ElasticSearch implements SearchContract
{
    private $search;

    /**
     * Default constructor.
     * 
     * @param ElasticSearch\Client $client
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->search = $client;
    }

    /**
     * Elastic will automatically create a basic index when the very first document is submitted.
     * That index is created with bare-bones settings though, so if we want more advanced index options
     * (such as stemming) we want to create the index BEFORE actually submitting a document.
     * 
     * Creating an index with the PHP elasticclient:
     * https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_index_management_operations.html
     * 
     * Creating index with word stemming, etc:
     * https://www.elastic.co/guide/en/elasticsearch/reference/6.2/mixing-exact-search-with-stemming.html
     * 
     * Using the phrase suggester (or the term suggester):
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-suggesters-phrase.html
     * 
     * The post data being indexed:
     *      'id' => $this->id,
     *      'parent_id' => $this->parent_id,
     *      'author' => $this->author_c,
     *      'body' => $this->body_c,
     *      'date' => $this->date,
     * 
     */
    public function createChattyPostIndex()
    {
        // Define two indices on 'body': 
        $indexParams = [
            'index' => app_setting::getPostSearchIndex(),
            'body' => [
                'settings' => [
                    'analysis' => [
                        'filter' => [
                            // Stopwords filter removes unhelpful words (and, the, etc)
                            'my_english_stop' => [
                                'type' => 'stop',
                                'stopwords' => '_english_'
                            ],                            
                            'shingle' => [
                                'type' => 'shingle',
                                'min_shingle_size' => 2,
                                'max_shingle_size' => 3
                            ],
                        ],
                        // https://www.elastic.co/guide/en/elasticsearch/guide/current/char-filters.html
                        'char_filter' => [
                            'quotes' => [
                                'type' => 'mapping',
                                'mappings' => [
                                    '\\u0091 => \\u0027',
                                    '\\u0092 => \\u0027',
                                    '\\u2018 => \\u0027',
                                    '\\u2019 => \\u0027',
                                    '\\u201B => \\u0027',
                                ],
                            ],
                        ],
                        'analyzer' => [
                            'english_exact' => [
                                'tokenizer' => 'standard',
                                'char_filter' => [
                                    'quotes'
                                ],
                                'filter' => [
                                    'lowercase'
                                ]
                            ],
                            'trigram' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'char_filter' => [
                                    'quotes'
                                ],
                                'filter' => [
                                    'standard',
                                    'shingle'
                                ]
                            ],
                            'reverse' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'char_filter' => [
                                    'quotes'
                                ],
                                'filter' => [
                                    'standard',
                                    'reverse'
                                ]
                            ],
                            /*
                            'classic_tokens' => [
                                'tokenizer' => 'classic',
                                'filter' => [
                                    'my_english_stop'
                                ]
                            ],
                            */
                            'english_mterms' => [
                                /*'tokenizer' => 'standard',*/
                                'tokenizer' => 'uax_url_email',
                                'char_filter' => [
                                    'quotes'
                                ],
                                'filter' => [
                                    'my_english_stop',
                                    'lowercase'
                                ],
                            ],
                        ],
                    ]
                ],
                'mappings' => [
                    app_setting::getPostSearchType() => [
                        'properties' => [
                            'body' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'fields' => [
                                    'exact' => [
                                        'type' => 'text',
                                        'analyzer' => 'english_exact'
                                    ],
                                    'trigram' => [
                                        'type' => 'text',
                                        'analyzer' => 'trigram'
                                    ],
                                    'reverse' => [
                                        'type' => 'text',
                                        'analyzer' => 'reverse'
                                    ],
                                    'mterms' => [
                                        'type' => 'text',
                                        'analyzer' => 'english_mterms'
                                    ],
                                ]
                            ],
                            'date' => [
                                'type' => 'date',
                                'format' => 'yyyy-MM-dd H:m:s'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->search->indices()->create($indexParams);

    }

    /**
     * Searches App\Chatty\post model for given string and returns Collection of results
     * 
     * @param String $queryString
     * @return mixed (Eloquent Collection of ElasticSearch results)
     */
    public function searchCollection($queryString): Collection
    {
        $items = $this->searchOnElasticSearch($queryString);

        return $this->buildCollection($items);
    }

    /**
     * Searches App\Chatty\post model for given string and returns a multi-dimensional
     * array with '_id' as the key and '_score' as the value.
     * 
     * @param String $queryString
     * @return mixed (PHP array of Post IDs)
     */
    public function searchArray($queryString): array
    {
        $items = $this->searchOnElasticSearch($queryString);

        return $this->buildIdArray($items);
    }

    /**
     * Searches App\Chatty\post model for given string and returns both an array of Post ID's
     * and any suggestions.
     * 
     * @param String $queryString
     * @return mixed (PHP array-of-arrays with Post Id's in one and Suggestions in the other)
     */
    public function simpleQueryStringWithSuggestions($body,$author,$rootPosts,$from,$to): array
    {
        $returnArr = [];
        $returnArr['postIdsAndScores'] = [];
        $returnArr['suggestions'] = [];

        // Returns a multidimensional array containing both search results and any suggestions
        $items = $this->performSimpleQueryStringWithSuggestions($body,$author,$rootPosts,$from,$to);

        // Grab a multi-dimensional array of _id's and _score's
        $returnArr['postIdsAndScores'] = $this->buildIdArray($items);

        // Pull the suggestions from the search results
        $returnArr['suggestions'] = $this->buildSuggestionArray($items);

        return $returnArr;
    }

    /**
     *  Perform an Elastic 'match' full-text query and return both an array of Post ID's
     *  and any suggestions.
     * 
     *  @param String $queryString
     *  @return mixed (PHP array-of-arrays with Post Id's in one and Suggestions in the other)
     */
    public function matchQueryWithSuggestions($body,$author,$rootPosts,$from,$to): array
    {
        $returnArr = [];
        $returnArr['postIdsAndScores'] = [];
        $returnArr['suggestions'] = [];

        // Returns a multidimensional array containing both search results and any suggestions
        $items = $this->performMatchQueryWithSuggestions($body,$author,$rootPosts,$from,$to);

        // Grab a multi-dimensional array of _id's and _score's
        $returnArr['postIdsAndScores'] = $this->buildIdArray($items);

        // Pull the suggestions from the search results
        $returnArr['suggestions'] = $this->buildSuggestionArray($items);

        return $returnArr;
    }

    /**
     *  Perform an Elastic 'common terms' full-text query and return both an array of Post ID's
     *  and any suggestions.
     * 
     */
    public function commonTermsQueryWithSuggestions($body,$author,$rootPosts,$from,$to): array
    {
        $returnArr = [];
        $returnArr['postIdsAndScores'] = [];
        $returnArr['suggestions'] = [];

        // Returns a multidimensional array containing both search results and any suggestions
        $items = $this->performCommonTermsQueryWithSuggestions($body,$author,$rootPosts,$from,$to);

        // Grab a multi-dimensional array of _id's and _score's
        $returnArr['postIdsAndScores'] = $this->buildIdArray($items);

        // Pull the suggestions from the search results
        $returnArr['suggestions'] = $this->buildSuggestionArray($items);

        return $returnArr;
    }

    /**
     * Return a list of the most popular words in a users posts.
     */
    public function termVectorsForAuthor($author,$from,$to)
    {
        $items = $this->countAuthorTerms($author,$from,$to);
        
        return $items;
    }

    /**
     * @param Array $postIds
     * @return JSON term statistics
     */
    public function termVectorsWithScoreForPostIds($postIds)
    {
        $items = $this->countTermsWithScoreForPostIds($postIds);
        
        return $items;
    }


    /** 
     * Return the term vectors for a particular post ID.
     */
    public function termVectorsForPost($postId)
    {
        $items = $this->countPostTerms($postId);

        return $items;
    }

    /** 
     * Return the term vectors for a particular post ID.
     */
    public function termVectorsForPostWithScore($postId)
    {
        $items = $this->countPostTermsWithScore($postId);

        return $items;
    }

    /**
     *  Query the post IDs behind a term in a word cloud.
     */
    public function getPostsForTerm($term,$author,$from,$to)
    {
        // Actually call the Elastic query to return all post id's with that term in that timeframe
        $items = $this->queryPostIdsForCloudTerm($term,$author,$from,$to);

        // From the returned JSON, pluck all the post ID's out and store them in an array
        $idArray = $this->getPostIdsForTerm($items);

        /**
         *  The above function calls queryPostIdsForCloudTerm which works pretty good *EXCEPT*
         *  for one condition: when the author's name contains a space or punctuation. Because
         *  the 'author' field in Elastic is a text field, it's subject to analysis when used as
         *  part of either a MATCH query (which we're doing) or a filter (which we're not).
         * 
         *  So if I pass 'the man with the briefcase' for the term 'switch' it'll also match
         *  'switch' for 'rag and bone man' and 'hanged man' and every other Shacker with ' man '
         *  in their name.
         * 
         *  The way we worked around this in the SearchController was doing a PostgreSQL query 
         *  afterwards which confirmed that all the post id's returned from Elastic are actually
         *  by that author in the RDBMS.
         */
        $postsToDiscard = NULL;
        if($author != app_setting::dailyCloudUser())
        {
            $postsToDiscard = DB::table('posts')                            // This gets the incorrect posts returned from Elastic
                                ->select('id')
                                ->whereNotIn('id', function($query) use ($author,$from,$to) {
                                    $query->select('id')                // This selects the correct posts (with right author)
                                        ->from('posts')
                                        ->where('author_c','ILIKE',$author)
                                        ->where('category','!=',postcategory::categoryId('nuked'))
                                        ->whereBetween('date',[$from,$to]);
                                })
                                ->whereIn('id',$idArray)                // This selects the posts from Elastic above (possibly with wrong author)
                                ->whereBetween('date',[$from,$to])
                                ->get();
        }
        // Daily Cloud doesn't care about authors but I still want to ensure no nuked posts get picked up or anything
        else
        {
            $postsToDiscard = DB::table('posts')
                                ->select('id')
                                ->whereNotIn('id', function($query) use ($author,$from,$to) {
                                    $query->select('id')
                                        ->from('posts')                 // Remove the author criteria as we want all posts
                                        ->where('category','!=',postcategory::categoryId('nuked'))
                                        ->whereBetween('date',[$from,$to]);
                                })
                                ->whereIn('id',$idArray)
                                ->whereBetween('date',[$from,$to])
                                ->get();

        }
        
        $postIds = [];
        foreach($postsToDiscard as $post) {
            $postIds[] = $post->id;
        }
        // Merge the two arrays, removing all the posts in $postIds from $idArray
        $cleansedArr = array_diff($idArray,$postIds); 

        return $cleansedArr;
    }

    /**
     *  For the top terms in a Word Cloud, query the posts behind them and extract the
     *  id and body. Then send the bodies off for tuple analysis so we can try some phrase
     *  generation.
     */
    public function generatePostTrigramsForTerm($termArr, $author, $from, $to)
    {
        $idArray = $this->getPostsForTerm($termArr,$author,$from,$to);

        $trigramsForTerm = $this->getTrigramsForTermPosts($termArr, $idArray);

        return $trigramsForTerm;
    }

    private function getTrigramsForTermPosts($term, $idArray)
    {
        $posts = DB::table('posts')
                        ->whereIn('id',$idArray)
                        ->get();
        
        $combinedBodyText = "";

        foreach($posts as $post) {
            $combinedBodyText .= $post->body_c . ". ";
        }

        $trigramArray = $this->generateTrigramsForText($combinedBodyText);

        $prunedArray = [];

        foreach($trigramArray["tokens"] as $item) {
            if(array_key_exists("positionLength",$item)) {
                if($item["positionLength"] >= 2) {
                    if(strpos(strtolower($item["token"]),strtolower($term)) !== FALSE) {
                        $prunedArray[] = strtolower($item["token"]);
                    }
                }
            }
        }
        
        return $prunedArray;
    }
    
    private function generateTrigramsForText($text)
    {
        $params = array(
            'analyzer' => 'trigram',
            'text' => $text
        );

        $header = array("content-type: application/json");
        $url = 'localhost:9200/shacknews_chatty_posts/_analyze';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($curl);
        curl_close($curl);

        return json_decode($result,true);

        //dd(json_decode($result,true));
        //returnArr = $this->buildTrigramArray(json_decode($result,true));

        //return $returnArr;
    }
    
    public function getTrigramsForPost($postID)
    {
        /*
                curl -X POST "localhost:9200/_analyze" -H 'Content-Type: application/json' -d'
                {
                "analyzer": "whitespace",
                "text":     "The quick brown fox."
                }
                '
                curl -X POST "localhost:9200/_analyze" -H 'Content-Type: application/json' -d'
                {
                "tokenizer": "standard",
                "filter":  [ "lowercase", "asciifolding" ],
                "text":      "Is this déja vu?"
                }
                '
        */

        $body = post::find($postID)->body_c;
/*
        $params = array(
            'analyzer' => 'trigram',
            'text' => $body
        );
*/
/*
        $items = $this->search->mtermvectors([
            'index' => app_setting::getPostSearchIndex(),
            'type' => app_setting::getPostSearchType(),
            'ids' => $idArray,
            'term_statistics' => true,
            'field_statistics' => false,
            'fields' => 'body.mterms',
            'offsets' => false,
            'positions' => false,
            'payloads' => false,
        ]);

        return $items;
*/
/*
        $header = array("content-type: application/json");
        $url = 'localhost:9200/shacknews_chatty_posts/_analyze';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($curl);
        curl_close($curl);

        //dd(json_decode($result,true));
        $returnArr = $this->buildTrigramArray(json_decode($result,true));

        return $returnArr;
*/
        return $this->buildTrigramArray($this->generateTrigramsForText($body));
    }

    /**
     * Submits a post to Elastic for indexing
     * 
     * @param App\Chatty\post $post
     * @return void
     */
    public function indexPost($post)
    {
        $result = $this->search->index([
            'index' => $post->getSearchIndex(),
            'type' => $post->getSearchType(),
            'id' => $post->id,
            'body' => $post->toSearchArray(),
        ]);

        return $result;
    }

    /**
     *  Submits an array of posts to Elastic for indexing.
     * 
     * https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_indexing_documents.html
     * 
     *  @param Illuminate\Support\Collection $postInstance
     *  @return void
     */
    public function indexPosts($posts)
    {
        $batchSize = app_setting::getIndexBatchSize();
        $param = ['body' => []];
        $responses = [];

        for($i = 0; $i < count($posts); $i++) {
            $params['body'][] = [
                'index' => [
                    '_index' => $posts[$i]->getSearchIndex(),
                    '_type' => $posts[$i]->getSearchType(),
                    '_id' => $posts[$i]->id,
                ]
            ];

            $params['body'][] = $posts[$i]->toSearchArray();

            // Every {x} posts, send the bulk request
            if($i % $batchSize == 0) {
                // Call the 'bulk' method instead of the 'index' method when sending multiple requests
                $responses[] = $this->search->bulk($params);

                // Erase the old bulk request
                $params = ['body' => []];
            }
        }

        // Send the last batch if it's not empty
        if(!empty($params['body'])) {
            $responses[] = $this->search->bulk($params);
        }

        return $responses;
    }

    /**
     *  As part of simple phrase generation, we need to identify all the posts from which a term originated.
     *  
     */
    private function queryPostIdsForCloudTerm($term,$author,$from,$to)
    {
        /*
          "query": {
            "bool" : {
                "should" : [
                    { "match" : { "body" : "kids" } } , 
                    { "match" : { "body" : "man" } }
                ],
                "must" : [
                    {"match" : { "author" : "calipsoii" }}
                ],
                "filter" : {
                    "range" : {
                    "date" : {
                        "gte" : "2018-10-09",
                        "lte" : "2018-11-10",
                        "format" : "yyyy-MM-dd"
                    }
                }
            },
            "minimum_should_match" : 1
            }
          }
        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'from' => 0,
            'size' => app_setting::getMaxSearchResults(),
            'body' => [
                'query' => [
                    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-decay
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'match' => [
                                        'body' => [
                                            'query' => $body,
                                            //'fuzziness' => 'auto',
                                            'zero_terms_query' => 'all',
                                            'cutoff_frequency' => 0.01,
                                            'minimum_should_match' => '2<75%',
                                        ],
                                    ],
                                ],
                                'should' => [
                                    'match_phrase' => [
                                        'body' => [
                                            'query' => $body,
                                            'zero_terms_query' => 'all',
                                            //'cutoff_frequency' => 0.01,
                                            //'minimum_should_match' => '2<75%',
                                        ],
                                    ],
                                ],
                                'should' => [
                                    'match' => [
                                        'body.exact' => [
                                            'query' => $body,
                                            'boost' => 3,
                                        ],
                                    ],
                                ],
                                'filter' => $filter,
                            ],
                        ]
                    ],
                ]
            ],
        ]);
        */

        $postInstance = new post;
        $filter = [];
        $must = [];
        $should = [];
/*
        $must[] = [
            'match' => [
                'body' => [
                    'query' => $term,
                    'minimum_should_match' => '1'
                ]
            ]
        ];
        $must[] = [
            'match' => [
                'body' => [
                    'query' => 'outdoors'
                ]
            ]
        ];
        $must[] = [
            'match' => [
                'body' => [
                    'query' => 'hamster'
                ]
            ]
        ];
*/
        if(isset($author)) {
            $must[] = [
                ['match' => [ 'author' => $author ]]
            ];
        }

        $termArr = explode(';',$term);
/*
        $must[] = [
            ['match' => [ 'author' => 'calipsoii']]
        ];
*/
        foreach($termArr as $term) {
            $should[] = [ 'match' => [ 'body' => $term ]];
            /*$should[] = [ 'term' => [
                            'body' => $term
                        ]];*/
        }
/*
        $should[] = [ 
            ['match' => [ 'body' => $term]],
            ['match' => [ 'body' => 'project']],
            ['match' => [ 'body' => 'overwatch']],
        ];
/*
        $should[] = [
            'match' => [
                'body' => $term
            ]
        ];
        $should[] = [
            'match' => [
                'body' => 'project'
            ]
        ];
        $should[] = [
            'match' => [
                'body' => 'snipe'
            ]
        ];
*/
        $filter = [];
        if((isset($author)) && ($author == app_setting::dailyCloudUser())) {
            $filter[] = [
                'range' => [
                    'date' => [
                        //'gte' => $from."||/d",
                        //'lte' => $to."||/d",
                        'gte' => $from,
                        'lte' => $to,
                        //'format' => 'Y-m-d'
                        'format' => "yyyy-MM-dd H:m:s"
                    ]
                ]
            ];
        } else {
            $filter[] = [
                'range' => [
                    'date' => [
                        //'gte' => $from."||/d",
                        //'lte' => $to."||/d",
                        'gte' => $from,
                        'lte' => $to,
                        //'format' => 'Y-m-d'
                        'format' => "yyyy-MM-dd"
                    ]
                ]
            ];
        }

        $items = NULL;
        if((isset($author)) && ($author == app_setting::dailyCloudUser()))
        {
            $items = $this->search->search([
                '_source' => 'false',
                'index' => $postInstance->getSearchIndex(),
                'type' => $postInstance->getSearchType(),
                'from' => 0,
                'size' => app_setting::getMaxSearchResults(),
                'body' => [
                    'query' => [
                        'bool' => [
                            // We remove the MUST clause (removing the author limitation) if the daily cloud is calling this
                            'should' => $should,            
                            'filter' => $filter,
                            'minimum_should_match' => 1
                        ]
                    ]
                ]
            ]);
        }
        else
        {
            $items = $this->search->search([
                '_source' => 'false',
                'index' => $postInstance->getSearchIndex(),
                'type' => $postInstance->getSearchType(),
                'from' => 0,
                'size' => app_setting::getMaxSearchResults(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => $must,
                            'should' => $should,            
                            'filter' => $filter,
                            'minimum_should_match' => 1
                        ]
                    ]
                ]
            ]);
        }

        return $items;

    }

    /**
     * Actually perform the synchronous ElasticSearch call.
     * 
     * https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_search_operations.html
     * 
     * We probably want to enable this multi-field stuff:
     * https://www.elastic.co/guide/en/elasticsearch/reference/6.2/mixing-exact-search-with-stemming.html
     * 
     * @param String $body
     * @param String $author
     * @param Boolean $rootPosts
     * @param Date $from
     * @param Date $to
     * @return Array of ElasticSearch results (converted from JSON)
     */
    private function performSimpleQueryStringWithSuggestions($body,$author,$rootPosts,$from,$to): array
    {
        $postInstance = new post;
        /*
        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'body' => [
                'query' => [
                    'multi_match' => [
                        'fields' => ['body_c','author_c'],
                        'query' => $queryString,
                    ],
                ],
            ],
        ]);
        */
        /*
        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'from' => 0,
            'size' => 10000,
            'body' => [
                'query' => [
                    'match' => [
                        'body_c' => $queryString
                    ],
                ],
            ],
        ]);
        */

        $filter = [];

        if(isset($author)) {
            $filter[] = [
                'match' => [
                    'author' => $author
                ]
            ];
        }

        if($rootPosts) {
            $filter[] = [
                'term' => [
                    'parent_id' => 0
                ]
            ];
        }

        $filter[] = [
            'range' => [
                'date' => [
                    //'gte' => $from."||/d",
                    //'lte' => $to."||/d",
                    'gte' => $from,
                    'lte' => $to,
                    //'format' => 'Y-m-d'
                    'format' => "yyyy-MM-dd"
                ]
            ]
        ];

        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'from' => 0,
            'size' => app_setting::getMaxSearchResults(),
            'body' => [
                'query' => [
                    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-decay
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'simple_query_string' => [
                                        'query' => $body,
                                        'fields' => [
                                            'body',
                                        ],
                                        'quote_field_suffix' => '.exact',
                                        'default_operator' => 'or',
                                        'minimum_should_match' => '2<75%',
                                    ],
                                ],
                                'filter' => $filter,
                            ],
                        ],
                        'gauss' => [
                            'date' => [
                                'origin' => 'now',
                                // All documents newer than this offset are scored equally. For starters, I'm using the last year.
                                'offset' => '365d',
                                // First post in Winchatty is 1999-06-30. That's 18y10m from today. Offset is 1y. 
                                // Let's target 0.5 score for 2006 and low scores for < 2002. 
                                // Scale apparently doesn't support years so I need to use days?!?!!
                                // https://github.com/elastic/elasticsearch/issues/19619
                                'scale' => '2555d',
                                // Setting this a bit lower increases the steepness of score drop-off.
                                'decay' => '0.4'
                            ]
                        ]
                    ],
                ],
                'suggest' => [
                    'text' => $body,
                    'simple_phrase' => [
                        'phrase' => [
                            'field' => 'body.trigram',
                            'size' => 2,
                            'gram_size' => 3,
                            'direct_generator' => [
                                [
                                    'field' => 'body.trigram',
                                    'suggest_mode' => 'missing'
                                ]
                            ],
                            'highlight' => [
                                'pre_tag' => '<em>',
                                'post_tag' => '</em>'
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        return $items;
    }

    /**
     *  Elastic offers a few different full-text query engines. I've already tried the "simple query string" one
     *  which has the nice advantage of allowing the user to add conditions to their query string (+ - "").
     * 
     *  The 'match' query is the default full-text query and seems like it offers the most options.
     */
    private function performMatchQueryWithSuggestions($body,$author,$rootPosts,$from,$to)
    {
        $postInstance = new post;
        $filter = [];

        if(isset($author)) {
            $filter[] = [
                'match' => [
                    'author' => $author
                ]
            ];
        }

        if($rootPosts) {
            $filter[] = [
                'term' => [
                    'parent_id' => 0
                ]
            ];
        }

        $filter[] = [
            'range' => [
                'date' => [
                    'gte' => $from,
                    'lte' => $to,
                    'format' => "yyyy-MM-dd"
                ]
            ]
        ];

        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'from' => 0,
            'size' => app_setting::getMaxSearchResults(),
            'body' => [
                'query' => [
                    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-decay
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'match' => [
                                        'body' => [
                                            'query' => $body,
                                            //'fuzziness' => 'auto',
                                            'zero_terms_query' => 'all',
                                            'cutoff_frequency' => 0.01,
                                            'minimum_should_match' => '2<75%',
                                        ],
                                    ],
                                ],
                                'should' => [
                                    'match_phrase' => [
                                        'body' => [
                                            'query' => $body,
                                            'zero_terms_query' => 'all',
                                            //'cutoff_frequency' => 0.01,
                                            //'minimum_should_match' => '2<75%',
                                        ],
                                    ],
                                ],
                                'should' => [
                                    'match' => [
                                        'body.exact' => [
                                            'query' => $body,
                                            'boost' => 3,
                                        ],
                                    ],
                                ],
                                'filter' => $filter,
                            ],
                        ],
                        'gauss' => [
                            'date' => [
                                'origin' => 'now',
                                // All documents newer than this offset are scored equally. For starters, I'm using the last year.
                                'offset' => '365d',
                                // First post in Winchatty is 1999-06-30. That's 18y10m from today. Offset is 1y. 
                                // Let's target 0.5 score for 2006 and low scores for < 2002. 
                                // Scale apparently doesn't support years so I need to use days?!?!!
                                // https://github.com/elastic/elasticsearch/issues/19619
                                'scale' => '2555d',
                                // Setting this a bit lower increases the steepness of score drop-off.
                                'decay' => '0.4'
                            ]
                        ]
                    ],
                ],
                'suggest' => [
                    'text' => $body,
                    'simple_phrase' => [
                        'phrase' => [
                            'field' => 'body.trigram',
                            'size' => 2,
                            'gram_size' => 3,
                            'direct_generator' => [
                                [
                                    'field' => 'body.trigram',
                                    'suggest_mode' => 'missing'
                                ]
                            ],
                            'highlight' => [
                                'pre_tag' => '<em>',
                                'post_tag' => '</em>'
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        return $items;
    }

    /**
     *  A third query engine test: common terms query.
     *  https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-common-terms-query.html
     */
    private function performCommonTermsQueryWithSuggestions($body,$author,$rootPosts,$from,$to)
    {
        $postInstance = new post;
        $filter = [];

        if(isset($author)) {
            $filter[] = [
                'match' => [
                    'author' => $author
                ]
            ];
        }

        if($rootPosts) {
            $filter[] = [
                'term' => [
                    'parent_id' => 0
                ]
            ];
        }

        $filter[] = [
            'range' => [
                'date' => [
                    'gte' => $from,
                    'lte' => $to,
                    'format' => "yyyy-MM-dd"
                ]
            ]
        ];

        $items = $this->search->search([
            'index' => $postInstance->getSearchIndex(),
            'type' => $postInstance->getSearchType(),
            'from' => 0,
            'size' => app_setting::getMaxSearchResults(),
            'body' => [
                'query' => [
                    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-decay
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'common' => [
                                        'body' => [
                                            'query' => $body,
                                            'cutoff_frequency' => 0.05,
                                            'minimum_should_match' => [
                                                'low_freq' => '2<75%',
                                                'high_freq' => '90%',
                                            ],
                                        ],
                                    ],
                                ],
                                'filter' => $filter,
                            ],
                        ],
                        'gauss' => [
                            'date' => [
                                'origin' => 'now',
                                // All documents newer than this offset are scored equally. For starters, I'm using the last year.
                                'offset' => '365d',
                                // First post in Winchatty is 1999-06-30. That's 18y10m from today. Offset is 1y. 
                                // Let's target 0.5 score for 2006 and low scores for < 2002. 
                                // Scale apparently doesn't support years so I need to use days?!?!!
                                // https://github.com/elastic/elasticsearch/issues/19619
                                'scale' => '2555d',
                                // Setting this a bit lower increases the steepness of score drop-off.
                                'decay' => '0.4'
                            ]
                        ]
                    ],
                ],
                'suggest' => [
                    'text' => $body,
                    'simple_phrase' => [
                        'phrase' => [
                            'field' => 'body.trigram',
                            'size' => 2,
                            'gram_size' => 3,
                            'direct_generator' => [
                                [
                                    'field' => 'body.trigram',
                                    'suggest_mode' => 'missing'
                                ]
                            ],
                            'highlight' => [
                                'pre_tag' => '<em>',
                                'post_tag' => '</em>'
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        return $items;
    }

    /**
     * Return a listing of the most popular terms for a specific author.
     * 
     * https://stackoverflow.com/questions/27741717/elasticsearch-how-to-get-popular-words-list-of-documents?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
     * 
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-termvectors.html#docs-termvectors-terms-filtering
     * 
     * curl -X GET "localhost:9200/shacknews_chatty_posts/post/37471642/_termvectors?fields=body.whitespace"
     * 
     * https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/ElasticsearchPHP_Endpoints.html#Elasticsearch_Clienttermvectors_termvectors
     * 
     * https://www.elastic.co/guide/en/elasticsearch/reference/6.2/docs-multi-termvectors.html
     * 
     */
    private function countAuthorTerms($author,$from,$to)
    {
        $idArray = [];
        //$ids = DB::table('posts')->where('indexed','true')->orderBy('date','desc')->take(10)->get();
        $ids = DB::table('posts')->where([
                                    ['indexed','true'],
                                    ['author_c','ILIKE',$author],
                                ])
                                ->whereBetween('date',[$from,$to])
                                ->orderBy('date','desc')
                                ->get();
        foreach($ids as $id)
        {
            $idArray[] = $id->id;
        }

        $params = array(
            'ids' => $idArray,
            'parameters' => [
                'term_statistics' => true,
                'field_statistics' => false,
                'fields' => ['body.mterms'],
                'offsets' => false,
                'positions' => false,
                'payloads' => false,
/*                'filter' => [
                    'max_num_terms' => 1000,
                    'min_term_freq' => 1,
                    'min_doc_freq'=> 1,
                ],*/
            ],
        );
/*
        $items = $this->search->mtermvectors([
            'index' => app_setting::getPostSearchIndex(),
            'type' => app_setting::getPostSearchType(),
            'ids' => $idArray,
            'term_statistics' => true,
            'field_statistics' => false,
            'fields' => 'body.mterms',
            'offsets' => false,
            'positions' => false,
            'payloads' => false,
        ]);

        return $items;
*/
        $header = array("content-type: application/json");
        $url = 'localhost:9200/shacknews_chatty_posts/post/_mtermvectors';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    private function countTermsWithScoreForPostIds($postIds)
    {
        // Both of these are defined in the App Settings page, so make sure
        // to dynamically grab them in case the user changes them.
        $elasticIndex = app_setting::getPostSearchIndex();
        $elasticType = app_setting::getPostSearchType();

        $params = array(
            'ids' => $postIds,
            'parameters' => [
                'term_statistics' => true,
                'field_statistics' => false,
                'fields' => ['body.mterms'],
                'offsets' => false,
                'positions' => false,
                'payloads' => false,
                'filter' => [
                    'max_num_terms' => app_setting::getMaxSearchResults(),
                    'min_term_freq' => 1,
                    'min_doc_freq'=> 1,
                ],
            ],
        );

        $header = array("content-type: application/json");
        $url = 'localhost:9200/' . $elasticIndex . '/' . $elasticType . '/_mtermvectors';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;

    }

    private function countPostTerms($postId)
    {
        $items = $this->search->termvectors([
            'index' => app_setting::getPostSearchIndex(),
            'type' => app_setting::getPostSearchType(),
            'id' => $postId,
            'fields' => 'body.mterms',
            'term_statistics' => true,
            'field_statistics' => true,
            'positions' => false,
            'offsets' => false
        ]);

        return $items;
    }

    private function countPostTermsWithScore($postId)
    {
        $params = array(
            'term_statistics' => true,
            'field_statistics' => false,
            'fields' => ['body.mterms'],
            'offsets' => false,
            'positions' => false,
            'payloads' => false,
            'filter' => [
                'max_num_terms' => 500,
                'min_term_freq' => 1,
                'min_doc_freq'=> 1,
            ],
        );

        $header = array("content-type: application/json");
        $url = 'localhost:9200/shacknews_chatty_posts/post/'.$postId.'/_termvectors';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    /**
     * Build an Eloquent collection from the search results returned by Elastic.
     * 
     * @param Array of Elastic results
     * @return posts::collection
     */
    private function buildCollection(array $items): Collection
    {   
        /**
         * The data comes in a structure like this:
         * 
         * [ 
         *      'hits' => [ 
         *          'hits' => [ 
         *              [ '_source' => 1 ], 
         *              [ '_source' => 2 ], 
         *          ]
         *      ] 
         * ]
         * 
         * And we only care about the _source of the documents.
        */
        $hits = array_pluck($items['hits']['hits'],'_id') ?: [];

        return DB::table('posts')->whereIn('id',$hits)->get();

    }

    /**
     * Build an array of Post ID's from the ElasticSearch results. Include
     * score to display it to the end user.
     * 
     * @param Array of Elastic results (JSON)
     * @return Array of post ID's (suitable for building a collection)
     */
    private function buildIdArray(array $items): array
    {
        /**
         * The data comes in a structure like this:
         * 
         * [ 
         *      'hits' => [ 
         *          'hits' => [ 
         *              [ '_id' => 32000894 ],
         *              [ '_id' => 32000895 ],
         *          ]
         *      ] 
         * ]
         * 
         * We care about the _id and the _score of the results.
        */

        // Return a key-value array with _id as the key and _score as the value
        // $return = ['37002049' => '6.43245','36599304' => '4.23956']
        return array_pluck($items['hits']['hits'],'_score','_id') ?: [];
    }

    /**
     * Build an array of search suggestions from the ElasticSearch results.
     * 
     * @param Array of Elastic results (JSON)
     * @return Array of search suggestions (suitable for displaying to the user)
     */
    private function buildSuggestionArray(array $items): array
    {
        // When no suggestions are provided, the results look like this:
        /*
            array:5 [▼
                "took" => 39
                "timed_out" => false
                "_shards" => array:4 [▶]
                "hits" => array:3 [▼
                        "total" => 2
                        "max_score" => 7.4880695
                        "hits" => array:2 [▼
                        0 => array:5 [▼
                            "_index" => "shacknews_chatty_posts"
                            "_type" => "post"
                            "_id" => "37321794"
                            "_score" => 7.4880695
                            "_source" => array:5 [▶]
                        ]
                        1 => array:5 [▶]
                        ]
                    ]
                    "suggest" => array:1 [▼
                        "simple_phrase" => array:1 [▼
                            0 => array:4 [▼
                                "text" => "inertial dampers"
                                "offset" => 0
                                "length" => 16
                                "options" => []
                            ]
                    ]
                ]
            ]
        */

        // When Elastic returns suggestions, the results look like this:
        /*
            array:5 [▼
                "took" => 36
                "timed_out" => false
                "_shards" => array:4 [▶]
                    "hits" => array:3 [▼
                        "total" => 0
                        "max_score" => null
                        "hits" => []
                    ]
                    "suggest" => array:1 [▼
                        "simple_phrase" => array:1 [▼
                            0 => array:4 [▼
                                "text" => "inertial damperrs"
                                "offset" => 0
                                "length" => 17
                                "options" => array:1 [▼
                                0 => array:3 [▼
                                    "text" => "inertial dampers"
                                    "highlighted" => "inertial <em>dampers</em>"
                                    "score" => 0.019543217
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        */  

        // 

        return array_pluck($items['suggest']['simple_phrase'][0]['options'],'highlighted') ?: [];
    }

    /**
     * Take a bunch of terms, find their associated posts, build an array of Post ID's 
     * from the ElasticSearch results. Include body so we can send it for tuple analysis.
     * 
     * @param Array of Elastic results (JSON)
     * @return Array of post ID's (suitable for building a collection)
     */
    private function getPostIdsForTerm(array $items): array
    {
        /**
         * The data comes in a structure like this:
         * 
         * [ 
         *      'hits' => [ 
         *          'hits' => [ 
         *              [ '_id' => 32000894 ],
         *              [ '_id' => 32000895 ],
         *          ]
         *      ] 
         * ]
         * 
         * We care about the _id and the _body of the results.
        */

        // Return a key-value array with _id as the key and _body as the value
        // $return = ['37002049' => 'Hello world!','36599304' => 'Hi There!']
        //dd($items);
        //return array_pluck($items['hits']['hits'], '_source.body', '_source.id');
        return array_pluck($items['hits']['hits'], '_id') ?: [];
        //return array_pluck($items['hits']['hits']['_source'],'_body','_id') ?: [];
    }

    private function findTrigramArrayItems($item)
    {
        //dd($item);
        if(array_key_exists('positionLength',$item)) {
            if($item['positionLength'] == 3) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function buildTrigramArray(array $items): array
    {
        /* {"token":"are currently no","start_offset":345,"end_offset":361,"type":"shingle","position":45,"positionLength":3} */
        /* {"token":"currently","start_offset":349,"end_offset":358,"type":"<ALPHANUM>","position":46} */
        /**
         * The data comes in a structure like this:
         * 
         * [ 
         *      'tokens' => [ 
         *          0 => [
         *              'token' => 'are currently no',
         *              'start_offset' => 345,
         *              'end_offset' => 361,
         *              'type' => 'shingle',
         *              'position' => 45,
         *              'positionLength' => 3,
         *          ],
         *          1 => [
         *              'token' => 'currently',
         *              'start_offset' => 349,
         *              'end_offset' => 358,
         *              'type' => '<ALPHANUM>',
         *              'position' => 46,
         *              'positionLength' => 3,
         *          ]
         *      ] 
         * ]
         * 
         * We care about the token
        */
        
        /*
        foreach($items as $item) {
            $prunedArray = array_filter($items, array($this,"findTrigramArrayItems"));
        }
        dd($prunedArray);
        */
        // Return a key-value array with <token> as the key and <type> as the value
        // $return = ['37002049' => '6.43245','36599304' => '4.23956']
        return array_pluck($items['tokens'],'type','token') ?: [];
    }
}