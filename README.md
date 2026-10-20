# \## 🏏 CricketIQ — Batter Runs Predictor \& Matchup Analyzer

# 

# CricketIQ is a machine-learning-powered cricket analytics platform that predicts individual batter performance in IPL/T20 matches. It estimates expected runs and the probability of scoring 50 or more runs using historical IPL data and match context.

# 

# \### ✨ Key Features

# 

# \- \*\*Runs Prediction:\*\* Predicts expected runs using a Random Forest Regressor.

# \- \*\*Half-Century Probability:\*\* Estimates the chance of scoring 50+ runs using XGBoost.

# \- \*\*Player Analytics:\*\* Analyzes recent form, batting average, and strike rate.

# \- \*\*Venue \& Opponent Analysis:\*\* Considers historical ground performance and records against opposing franchises.

# \- \*\*Matchup Validation:\*\* Prevents invalid selections against a player's own franchise.

# \- \*\*Interactive Dashboard:\*\* Provides real-time predictions through a responsive web interface.

# 

# \### 🛠️ Tech Stack

# 

# \- \*\*Frontend:\*\* PHP, HTML, CSS, JavaScript

# \- \*\*Backend:\*\* Python, FastAPI

# \- \*\*Machine Learning:\*\* Scikit-learn, Random Forest, XGBoost

# \- \*\*Dataset:\*\* Historical IPL ball-by-ball data

# 

# \### 🚀 Getting Started

# 

# \*\*1. Install Dependencies\*\*

# `pip install -r requirements.txt`

# 

# \*\*2. Start the API Server\*\*

# `uvicorn main:app --host 127.0.0.1 --port 8000 --reload`

# 

# \*\*3. Start the PHP Frontend\*\*

# `php -S 127.0.0.1:8080`

# 

# Open \*\*http://127.0.0.1:8080/index.php\*\* in your browser.

# 

# API documentation: \*\*http://127.0.0.1:8000/docs\*\*

# 

# \### 📂 Project Structure

# 

# &#x20;   CricketIQ/

# &#x20;   ├── data/                        # IPL dataset and player statistics

# &#x20;   ├── main.py                      # FastAPI prediction API

# &#x20;   ├── index.php                    # Web application interface

# &#x20;   ├── extract\_players.py           # Data processing pipeline

# &#x20;   ├── requirements.txt             # Python dependencies

# &#x20;   ├── cricket\_runs\_predictor\_model.pkl

# &#x20;   ├── cricket\_50\_runs\_classifier\_model.json

# &#x20;   ├── model\_features\_list.pkl

# &#x20;   └── README.md

# 

# \### 📊 Prediction Models

# 

# | Model | Purpose |

# |---|---|

# | Random Forest Regressor | Predicts expected batter runs |

# | XGBoost Classifier | Predicts the probability of scoring 50+ runs |

