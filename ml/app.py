from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ---------------- DB CONNECT ----------------
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST"),
        database=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        port=os.getenv("DB_PORT", "5432")
    )

# ---------------- LOAD DATA ----------------
def get_data():
    try:
        conn = get_connection()

        query = """
        SELECT 
            title,
            COALESCE(participants,0) AS participants,
            COALESCE(budget,1) AS budget
        FROM activities
        """

        df = pd.read_sql_query(query, conn)
        conn.close()

        df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
        df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)

        return df

    except Exception as e:
        print("DB ERROR:", e)
        return pd.DataFrame()

# ---------------- TRAIN MODEL ----------------
def train_model(df):
    if df.empty:
        return None

    df["ratio"] = df["participants"] / df["budget"]

    X = df[["participants", "budget", "ratio"]]
    y = (df["participants"] * 0.7) + (df["ratio"] * 100)

    model = RandomForestRegressor(n_estimators=50, random_state=42)
    model.fit(X, y)

    return model

# ---------------- HOME ----------------
@app.route("/")
def home():
    return {"status": "ML API Running"}

# ---------------- PREDICT (FIXED OUTPUT) ----------------
@app.route("/predict", methods=["POST", "GET"])
def predict():
    try:
        df = get_data()

        model = train_model(df)

        if model is None:
            return jsonify({"error": "No data found"}), 400

        df["ratio"] = df["participants"] / df["budget"]

        X = df[["participants", "budget", "ratio"]]
        df["score"] = model.predict(X)

        # 🎯 SIMPLE ML DECISION LOGIC
        avg_score = df["score"].mean()

        if avg_score > 80:
            prediction = "High Performance Barangay"
            recommendation = "Maintain or slightly increase budget"
        elif avg_score > 50:
            prediction = "Moderate Performance"
            recommendation = "Improve project execution"
        else:
            prediction = "Low Performance"
            recommendation = "Review project planning and funding"

        return jsonify({
            "prediction": prediction,
            "score": float(avg_score),
            "recommendation": recommendation
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ---------------- RUN ----------------
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)