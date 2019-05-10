# ShackSearch

ShackSearch is an ElasticSearch-powered search engine for the Shacknews Chatty community. The basics:
* It's built on the Laravel PHP framework. 
* It uses electroly's Winchatty v2 API to retrieve Chatty posts.
* It uses a PostgreSQL database to store Chatty posts. 
* It uses ElasticSearch to crawl those posts and provide query results.
* It uses ElasticSearch meta-statistics to generate interactive Word Clouds.
* It provides a web interface to view threads, submit search queries and generate Word Clouds.
* It uses WinChatty V2 for user authentication and the Laravel security model for user authorization.
* It does **not** allow Chatty posting or LOL-tagging.

This repository contains all the code I wrote to create the Laravel web application. It (currently) does not contain the setup/configuration instructions required to prepare the server for hosting the application.
