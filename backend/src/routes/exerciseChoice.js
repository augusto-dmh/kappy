import { Router } from "express";
import exerciseChoiceController from "../controllers/exerciseChoice";

const router = new Router();

router.post("/exercises-choices", exerciseChoiceController.store);
router.get("/exercises-choices", exerciseChoiceController.index);
router.get("/exercises-choices/:id", exerciseChoiceController.show);
router.put("/exercises-choices/:id", exerciseChoiceController.update);
router.delete("/exercises-choices/:id", exerciseChoiceController.destroy);

export default router;
