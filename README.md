<small><b>🏏 CricketIQ — Batter Runs Predictor \& Matchup Analyzer</b></small>



<small>CricketIQ is a machine-learning-powered cricket analytics platform that predicts individual batter performance in IPL/T20 matches. It estimates expected runs and the probability of scoring 50 or more runs using historical IPL data and match context.</small>



<small><b>✨ Key Features</b></small>



\- <small><b>Runs Prediction:</b> Predicts expected runs using a Random Forest Regressor.</small>

\- <small><b>Half-Century Probability:</b> Estimates the chance of scoring 50+ runs using XGBoost.</small>

\- <small><b>Player Analytics:</b> Analyzes recent form, batting average, and strike rate.</small>

\- <small><b>Venue \& Opponent Analysis:</b> Considers historical ground performance and records against opposing franchises.</small>

\- <small><b>Matchup Validation:</b> Prevents invalid selections against a player's own franchise.</small>

\- <small><b>Interactive Dashboard:</b> Provides real-time predictions through a responsive web interface.</small>



<small><b>🛠️ Tech Stack</b></small>



\- <small><b>Frontend:</b> PHP, HTML, CSS, JavaScript</small>

\- <small><b>Backend:</b> Python, FastAPI</small>

\- <small><b>Machine Learning:</b> Scikit-learn, Random Forest, XGBoost</small>

\- <small><b>Dataset:</b> Historical IPL ball-by-ball data</small>



<small><b>🚀 Getting Started</b></small>



<small><b>1. Install Dependencies</b></small>

```bash

pip install -r requirements.txt

```



<small><b>2. Start the API Server</b></small>

```bash

uvicorn main:app --host 127.0.0.1 --port 8000 --reload

```



<small><b>3. Start the PHP Frontend</b></small>

```bash

php -S 127.0.0.1:8080

```



<small>Open <b>http://127.0.0.1:8080/index.php</b> in your browser.</small>

<small>API documentation: <b>http://127.0.0.1:8000/docs</b></small>



<small><b>📂 Project Structure</b></small>



```text

CricketIQ/

├── data/                       # IPL dataset and player statistics

├── main.py                     # FastAPI prediction API

├── index.php                   # Web application interface

├── extract\_players.py          # Data processing pipeline

├── requirements.txt            # Python dependencies

├── cricket\_runs\_predictor\_model.pkl

├── cricket\_50\_runs\_classifier\_model.json

├── model\_features\_list.pkl

└── README.md

```



<small><b>📊 Prediction Models</b></small>



| <small>Model</small> | <small>Purpose</small> |

|---|---|

| <small>Random Forest Regressor</small> | <small>Predicts expected batter runs</small> |

| <small>XGBoost Classifier</small> | <small>Predicts the probability of scoring 50+ runs</small> |

