Tiny Tiny RSS
=============

**NOTE: This is not the original TT-RSS, but a fork to clean, secure and modernize it. The original author(s) are welcome
to integrate our code back into the original project. Everyone is welcome to contribute.**

Web-based news feed aggregator, designed to allow you to read news from
any location, while feeling as close to a real desktop application as possible.

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/codysnider/tt-rss/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/codysnider/tt-rss/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/codysnider/tt-rss/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/codysnider/tt-rss/?branch=master)

## Installation & Usage

Currently, Docker is the recommended approach. Please ensure Docker and the docker-compose commands are installed, up-to-date and available.

To start the application, browser to the root directory of this repository and run:
```bash
docker-compose up -d
```

To initialize the database, run:
```bash
./bin/migrate_db
```

Uses Silk icons by Mark James: http://www.famfamfam.com/lab/icons/silk/
