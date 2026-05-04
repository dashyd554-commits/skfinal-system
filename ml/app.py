from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ================= DB CONNECTION =================
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST"),
        database=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        port=os.getenv("DB_PORT", "5432")
    )

# ================= LOAD DATA =================
def load_data():
    conn = get_connection()

    query = """
    SELECT 
        COALESCE(participants,0) AS participants,
        COALESCE(allocated_budget,1) AS budget,
        COALESCE(evaluation_score,0) AS evaluation_score
    FROM activities
    WHERE allocated_budget IS NOT NULL
    LIMIT 500
    """

    df = pd.read_sql_query(query, conn)
    conn.close()

    df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
    df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)
    df["evaluation_score"] = pd.to_numeric(df["evaluation_score"], errors="coerce").fillna(0)

    df["efficiency"] = df["participants"] / (df["budget"] + 1)
    df["quality"] = df["evaluation_score"] / 100

    return df

# ================= TRAIN MODEL =================
def train_model(df):
    X = df[["participants", "budget", "efficiency", "quality"]]
    y = (df["efficiency"] * 50) + (df["quality"] * 50)

    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    return model

# ================= ROOT TEST =================
@app.route("/")
def home():
    return jsonify({
        "status": "running",
        "message": "Flask API is working"
    })

# ================= PREDICT API =================
@app.route("/predict")
def predict():
    try:
        df = load_data()

        if df.empty:
            return jsonify({"error": "No data"}), 400

        model = train_model(df)
        X = df[["participants", "budget", "efficiency", "quality"]]

        df["score"] = model.predict(X)

        return jsonify({
            "status": "ok",
            "mean_score": float(df["score"].mean())
        })

    except Exception as e:
        return jsonify({
            "status": "error",
            "message": str(e)
        }), 500


# ================= RUN (RENDER FIX) =================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)