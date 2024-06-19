import { Router } from "express";
import userController from "../controllers/user";

const router = new Router();

router.post("/users", userController.store);
router.get("/users/:id", userController.index);
router.get("/users/user", userController.show);
router.put("/users/:id", userController.update);
router.delete("/users/:id", userController.destroy);

export default router;
