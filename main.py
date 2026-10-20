from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import joblib
import pandas as pd
import xgboost as xgb

# API অ্যাপ তৈরি
app = FastAPI(title="CricketIQ Prediction API")

# Enable CORS for frontend client interactions
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# মডেল এবং ফিচার লিস্ট লোড করা
run_predictor = joblib.load('cricket_runs_predictor_model.pkl')
features = joblib.load('model_features_list.pkl')

# XGBoost মডেলটি JSON থেকে নিরাপদে লোড করা
fifty_classifier = xgb.XGBClassifier()
fifty_classifier.load_model('cricket_50_runs_classifier_model.json')

# PHP থেকে যে ডেটাগুলো আসবে, তার স্ট্রাকচার
class BatterData(BaseModel):
    recent_average: float
    recent_strike_rate: float
    venue_average: float
    opponent_average: float
    bat_pos: int
    innings: int
    is_tailender: int
@app.get("/")
def home():
    return {
        "status": "online",
        "service": "CricketIQ Prediction API",
        "endpoint": "POST /predict",
        "documentation": "http://127.0.0.1:8000/docs",
        "notice": "This is the Python ML backend. To access the PHP frontend, open your PHP server URL (e.g. http://127.0.0.1:8080/index.php)."
    }

@app.post("/predict")
def predict_performance(data: BatterData):
    # ইনপুট ডেটাকে Pandas DataFrame-এ রূপান্তর
    input_df = pd.DataFrame([data.dict()])
    
    # ১. Random Forest (run_predictor) এর জন্য শুধু তার চেনা ফিচারগুলো আলাদা করা
    rf_features = run_predictor.feature_names_in_
    rf_input = input_df[rf_features]
    predicted_runs = float(run_predictor.predict(rf_input)[0])
    
    # ২. XGBoost (fifty_classifier) এর জন্য সবগুলো ফিচার আলাদা করা
    xgb_input = input_df[features]
    fifty_probability = float(fifty_classifier.predict_proba(xgb_input)[0][1])
    
    # রেজাল্ট ফেরত পাঠানো (JSON ফরম্যাটে)
    return {
        "status": "success",
        "expected_runs": int(round(predicted_runs)),
        "exact_expected_runs": float(round(predicted_runs, 1)),
        "raw_expected_runs": float(round(predicted_runs, 2)),
        "half_century_probability_percent": float(round(fifty_probability * 100, 2))
    }