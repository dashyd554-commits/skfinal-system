from flask import Flask, jsonify, request
from flask_cors import CORS
import os

app = Flask(__name__)
CORS(app)

@app.route("/")
def home():
    return "SK ML API RUNNING"

@app.route("/predict", methods=["POST"])
def predict():

    try:
        data = request.get_json()

        total_budget = float(data.get("total_budget", 0))
        used_budget = float(data.get("used_budget", 0))
        remaining_budget = float(data.get("remaining_budget", 0))
        approved_projects = int(data.get("approved_projects", 0))
        rejected_projects = int(data.get("rejected_projects", 0))
        pending_projects = int(data.get("pending_projects", 0))
        total_projects = int(data.get("total_projects", 0))

        if total_budget <= 0:
            return jsonify({"error": "No budget data"}), 400

        utilization = (used_budget / total_budget) * 100 if total_budget > 0 else 0
        approval_rate = (approved_projects / total_projects) * 100 if total_projects > 0 else 0
        rejection_penalty = (rejected_projects / total_projects) * 100 if total_projects > 0 else 0

        score = (
            utilization * 0.35 +
            approval_rate * 0.45 +
            (100 - rejection_penalty) * 0.20
        )

        score = round(score, 2)

        if score >= 75:
            category = "High Performance"
            success_probability = 0.88
            recommendation = "Barangay operations are excellent. Maintain active youth programs and continue strategic funding."
        elif score >= 45:
            category = "Moderate Performance"
            success_probability = 0.64
            recommendation = "Barangay performance is stable. Increase proposal completion rate and improve budget usage."
        else:
            category = "Low Performance"
            success_probability = 0.32
            recommendation = "Barangay performance is below target. Strengthen planning, proposal approval, and project implementation."

        return jsonify({
            "status": "ok",
            "category": category,
            "success_probability": success_probability,
            "budget_efficiency_score": score,
            "recommendation": recommendation
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)