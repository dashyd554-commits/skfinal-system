from flask import Flask, jsonify, request
import pandas as pd
import psycopg2
import pickle
import numpy as np
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ---------------- DB CONNECTION ----------------
conn = psycopg2.connect(
    host="localhost",
    database="sk_system",
    user="postgres",
    password="your_password",
    port=5432
)

# ---------------- LOAD DATA ----------------
def get_data():
    query = """
    SELECT 
        title,
        COALESCE(participants,0) AS participants,
        1 AS budget
    FROM activities
    """
    df = pd.read_sql_query(query, conn)
    return df

# ---------------- TRAIN MODEL ----------------
def train_model():
    df = get_data()

    if df.empty:
        return None

    df["ratio"] = df["participants"] / df["budget"]

    X = df[["participants", "budget", "ratio"]]
    y = df["participants"] * 0.7 + df["ratio"] * 100

    model = RandomForestRegressor(n_estimators=50, random_state=42)
    model.fit(X, y)

    return model, df

# ---------------- API ROUTE ----------------
@app.route("/predict", methods=["GET"])
def predict():
    result = train_model()

    if result is None:
        return jsonify({"error": "No data found"}), 400

    model, df = result

    df["ratio"] = df["participants"] / df["budget"]
    X = df[["participants", "budget", "ratio"]]

    df["score"] = model.predict(X)

    return jsonify(df.to_dict(orient="records"))

# ---------------- HOME ----------------
@app.route("/")
def home():
    return {"status": "ML API Running"}

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)