from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ---------------- DB CONNECTION (RENDER SAFE) ----------------
conn = psycopg2.connect(
    host=os.getenv("DB_HOST", "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com"),
    database=os.getenv("DB_NAME", "sk_system"),
    user=os.getenv("DB_USER", "sk_admin"),
    password=os.getenv("DB_PASSWORD", "vnEwS9NI5pkc7khmhNCMfvbjbID5YAtm"),
    port=os.getenv("DB_PORT", "5432")
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

    # FORCE numeric (fix string issues)
    df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
    df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)

    return df

# ---------------- TRAIN MODEL ----------------
def train_model(df):
    if df is None or df.empty:
        return None

    df["ratio"] = df["participants"] / df["budget"]

    X = df[["participants", "budget", "ratio"]]

    # simple stable ML formula
    y = (df["participants"] * 0.7) + (df["ratio"] * 100)

    model = RandomForestRegressor(n_estimators=50, random_state=42)
    model.fit(X, y)

    return model, df


# ---------------- PREDICT ROUTE ----------------
@app.route("/predict", methods=["GET"])
def predict():

    df = get_data()

    result = train_model(df)

    if result is None:
        return jsonify({"error": "No data found in database"}), 400

    model, df = result

    df["ratio"] = df["participants"] / df["budget"]

    X = df[["participants", "budget", "ratio"]]

    df["score"] = model.predict(X)

    return jsonify(df.to_dict(orient="records"))


# ---------------- HOME ----------------
@app.route("/")
def home():
    return {"status": "ML API Running on Render"}

# ---------------- IMPORTANT FOR RENDER ----------------
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)