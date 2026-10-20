# \# 🏏 CricketIQ — Batter Runs Predictor \& Matchup Analyzer

# 

# CricketIQ is a machine-learning-powered cricket analytics platform that predicts individual batter performance in IPL/T20 matches. It estimates expected runs and the probability of scoring 50 or more runs using historical IPL data and match context.

# 

# \## ✨ Key Features

# 

# \- \*\*Runs Prediction:\*\* Predicts expected runs using a Random Forest Regressor.

# \- \*\*Half-Century Probability:\*\* Estimates the chance of scoring 50+ runs using XGBoost.

# \- \*\*Player Analytics:\*\* Analyzes recent form, batting average, and strike rate.

# \- \*\*Venue \& Opponent Analysis:\*\* Considers historical ground performance and records against opposing franchises.

# \- \*\*Matchup Validation:\*\* Prevents invalid selections against a player's own franchise.

# \- \*\*Interactive Dashboard:\*\* Provides real-time predictions through a responsive web interface.

# 

# \## 🛠️ Tech Stack

# 

# \- \*\*Frontend:\*\* PHP, HTML, CSS, JavaScript

# \- \*\*Backend:\*\* Python, FastAPI

# \- \*\*Machine Learning:\*\* Scikit-learn, Random Forest, XGBoost

# \- \*\*Dataset:\*\* Historical IPL ball-by-ball data

# 

# \## 🚀 Getting Started

# 

# \### 1. Install Dependencies

# 

# ```bash

# pip install -r requirements.txt

# ```

# 

# \### 2. Start the API Server

# 

# ```bash

# uvicorn main:app --host 127.0.0.1 --port 8000 --reload

# ```

# 

# \### 3. Start the PHP Frontend

# 

# ```bash

# php -S 127.0.0.1:8080

# ```

# 

# Open \*\*http://127.0.0.1:8080/index.php\*\* in your browser.

# 

# API documentation: \*\*http://127.0.0.1:8000/docs\*\*

# 

# \## 📂 Project Structure

# 

# ```text

# CricketIQ/

# ├── data/                       # IPL dataset and player statistics

# ├── main.py                     # FastAPI prediction API

# ├── index.php                   # Web application interface

# ├── extract\_players.py           # Data processing pipeline

# ├── requirements.txt             # Python dependencies

# ├── cricket\_runs\_predictor\_model.pkl

# ├── cricket\_50\_runs\_classifier\_model.json

# ├── model\_features\_list.pkl

# └── README.md

# ```

# 

# \## 📊 Prediction Models

# 

# | Model | Purpose |

# |---|---|

# | Random Forest Regressor | Predicts expected batter runs |

# | XGBoost Classifier | Predicts the probability of scoring 50+ runs |

# 

# 



