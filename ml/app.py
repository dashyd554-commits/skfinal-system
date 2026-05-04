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

# ================= LOAD DATA (SAFE) =================
def load_data():
    try:
        conn = get_connection()

        query = """
        SELECT 
            COALESCE(participants,0) AS participants,
            COALESCE(allocated_budget,1) AS budget,
            COALESCE(evaluation_score,0) AS evaluation_score
        FROM activities
        LIMIT 200
        """

        df = pd.read_sql_query(query, conn)
        conn.close()

        if df.empty:
            return pd.DataFrame()

        # CLEAN DATA
        df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
        df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)
        df["evaluation_score"] = pd.to_numeric(df["evaluation_score"], errors="coerce").fillna(0)

        # PREVENT DIVIDE BY ZERO
        df["budget"] = df["budget"].apply(lambda x: max(x, 1))

        # FEATURES
        df["efficiency"] = df["participants"] / df["budget"]
        df["quality"] = df["evaluation_score"] / 100

        return df

    except Exception as e:
        print("DB ERROR:", e)
        return pd.DataFrame()

# ================= TRAIN MODEL =================
def train_model(df):
    X = df[["participants", "budget", "efficiency", "quality"]]

    # STABLE TARGET (NO EXPLODING VALUES)
    y = (
        df["efficiency"] * 50 +
        df["quality"] * 50
    )

    model = RandomForestRegressor(
        n_estimators=120,
        max_depth=10,
        random_state=42
    )

    model.fit(X, y)
    return model

# ================= ROOT =================
@app.route("/")
def home():
    return jsonify({
        "status": "ML API running",
        "endpoints": ["/predict"]
    })

# ================= PREDICT =================
@app.route("/predict")
def predict():
    df = load_data()

    if df.empty:
        return jsonify({
            "error": "No data found in activities table",
            "mean_score": 0,
            "status": "empty_dataset"
        }), 200

    model = train_model(df)

    X = df[["participants", "budget", "efficiency", "quality"]]
    df["score"] = model.predict(X)

    mean_score = float(df["score"].mean())

    return jsonify({
        "status": "ok",
        "mean_score": round(mean_score, 2),
        "rows": len(df)
    })

# ================= RUN =================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)