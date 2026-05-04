from flask import Flask, jsonify, request
import os

app = Flask(__name__)

@app.route("/")
def home():
    return "SK ML API RUNNING"

@app.route("/predict", methods=["POST"])
def predict():

    data = request.get_json()

    total_budget = float(data.get("total_budget", 0))
    used_budget = float(data.get("used_budget", 0))
    approved = int(data.get("approved_projects", 0))
    rejected = int(data.get("rejected_projects", 0))
    pending = int(data.get("pending_projects", 0))
    total = int(data.get("total_projects", 0))

    if total_budget <= 0:
        return jsonify({"error": "No data"}), 400

    utilization = (used_budget / total_budget) * 100

    approval_rate = (approved / total) * 100 if total > 0 else 0
    rejection_rate = (rejected / total) * 100 if total > 0 else 0

    score = (
        utilization * 0.4 +
        approval_rate * 0.4 +
        (100 - rejection_rate) * 0.2
    )

    score = round(score, 2)

    if score >= 75:
        category = "High Performance"
        prob = 0.85
        rec = "Excellent barangay performance. Continue scaling programs."
    elif score >= 40:
        category = "Moderate Performance"
        prob = 0.60
        rec = "Stable performance. Improve proposal execution."
    else:
        category = "Low Performance"
        prob = 0.35
        rec = "Needs improvement in planning and execution."

    return jsonify({
        "status": "ok",
        "category": category,
        "success_probability": prob,
        "budget_efficiency_score": score,
        "recommendation": rec
    })


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)