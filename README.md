<h2 align="center">DLSite list project with design inspired by MyAnimeList.net theme</h2>

## Requirements
1. [Python 3.10.11](https://www.python.org/downloads/release/python-31011) (tested on this version) and [pip](https://pypi.org/project/pip) (for easier installation process) 
2. Python modules (installed in venv):
    - dlsite-async
    - requests
    - pillow

## Installation process
#### Run commands from project folder
1. composer install
2. php artisan key:generate
3. php artisan migrate
4. php artisan storage:link

### Python modules/packages installation process (venv)
#### Run commands from project folder
1. python -m venv python/venv
2. source python/venv/bin/activate  
2.1 # On Windows use: python\venv\Scripts\activate
3. pip install -r python/requirements.txt