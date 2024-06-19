import { Router } from "express";
import exerciseController from "../controllers/exercise";

const router = new Router();

router.post("/exercises", exerciseController.store);
router.get("/exercises", exerciseController.index);
router.get("/exercises/:id", exerciseController.show);
router.put("/exercises/:id", exerciseController.update);
router.delete("/exercises/:id", exerciseController.destroy);

export default router;
