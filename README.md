<h2 align="center">DLSite list project with design based on MyAnimeList.net theme</h2>

## Version 0.4.3

## Requirements
1. [Python 3.10.11](https://www.python.org/downloads/release/python-31011) (tested on this version) and [pip](https://pypi.org/project/pip) (for easier installation process) in path / globally 
2. Python modules:
    - dlsite-async
    - requests

## Installation process
#### from project folder
1. composer install
2. php artisan key:generate
3. php artisan migrate
4. php artisan storage:link

### python modules/packages installation process
#### from project folder

1. pip install --target python/modules dlsite-async requests