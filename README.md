# <small>\*\*🏏 CricketIQ — Batter Runs Predictor \& Matchup Analyzer\*\*</small>

# 

# <small>CricketIQ is a machine-learning-powered cricket analytics platform that predicts individual batter performance in IPL/T20 matches. It estimates expected runs and the probability of scoring 50 or more runs using historical IPL data and match context.</small>

# 

# <small>\*\*✨ Key Features\*\*</small>

# 

# \- <small>\*\*Runs Prediction:\*\* Predicts expected runs using a Random Forest Regressor.</small>

# \- <small>\*\*Half-Century Probability:\*\* Estimates the chance of scoring 50+ runs using XGBoost.</small>

# \- <small>\*\*Player Analytics:\*\* Analyzes recent form, batting average, and strike rate.</small>

# \- <small>\*\*Venue \& Opponent Analysis:\*\* Considers historical ground performance and records against opposing franchises.</small>

# \- <small>\*\*Matchup Validation:\*\* Prevents invalid selections against a player's own franchise.</small>

# \- <small>\*\*Interactive Dashboard:\*\* Provides real-time predictions through a responsive web interface.</small>

# 

# <small>\*\*🛠️ Tech Stack\*\*</small>

# 

# \- <small>\*\*Frontend:\*\* PHP, HTML, CSS, JavaScript</small>

# \- <small>\*\*Backend:\*\* Python, FastAPI</small>

# \- <small>\*\*Machine Learning:\*\* Scikit-learn, Random Forest, XGBoost</small>

# \- <small>\*\*Dataset:\*\* Historical IPL ball-by-ball data</small>

# 

# <small>\*\*🚀 Getting Started\*\*</small>

# 

# <small>\*\*1. Install Dependencies\*\*</small>

# <small>`pip install -r requirements.txt`</small>

# 

# <small>\*\*2. Start the API Server\*\*</small>

# <small>`uvicorn main:app --host 127.0.0.1 --port 8000 --reload`</small>

# 

# <small>\*\*3. Start the PHP Frontend\*\*</small>

# <small>`php -S 127.0.0.1:8080`</small>

# 

# <small>Open `http://127.0.0.1:8080/index.php` in your browser.</small>

# <small>API documentation: `http://127.0.0.1:8000/docs`</small>

# 

# <small>\*\*📂 Project Structure\*\*</small>

# 

# <small>CricketIQ/</small>

# <small>├── data/                        # IPL dataset and player statistics</small>

# <small>├── main.py                      # FastAPI prediction API</small>

# <small>├── index.php                    # Web application interface</small>

# <small>├── extract\_players.py           # Data processing pipeline</small>

# <small>├── requirements.txt             # Python dependencies</small>

# <small>├── cricket\_runs\_predictor\_model.pkl</small>

# <small>├── cricket\_50\_runs\_classifier\_model.json</small>

# <small>├── model\_features\_list.pkl</small>

# <small>└── README.md</small>

# 

# <small>\*\*📊 Prediction Models\*\*</small>

# 

# \- <small>\*\*Random Forest Regressor:\*\* Predicts expected batter runs</small>

# \- <small>\*\*XGBoost Classifier:\*\* Predicts the probability of scoring 50+ runs</small>

